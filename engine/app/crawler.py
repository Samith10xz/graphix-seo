"""
Async site crawler.

- Breadth-first from the start URL, so `depth` = clicks from the home page.
- Stays on one host, respects robots.txt, skips non-HTML files.
- Sends finished pages to Laravel in batches (POST {callback_base}/pages), then the
  site-level summary (POST {callback_base}/finish). Page text is dropped from memory
  once sent, so large crawls stay light.
"""
from __future__ import annotations

import asyncio
import time
from urllib.parse import urlparse
from urllib.robotparser import RobotFileParser

import httpx

from .audit import analyze_page, analyze_site
from .config import settings

SKIP_EXT = (".jpg", ".jpeg", ".png", ".gif", ".webp", ".svg", ".pdf", ".zip", ".mp4", ".mp3",
            ".css", ".js", ".ico", ".xml", ".doc", ".docx", ".xls", ".xlsx")
BATCH_SIZE = 50


class Callback:
    """Posts results to Laravel. With no callback_base (tests, CLI) it does nothing."""

    def __init__(self, base: str | None):
        self.base = base.rstrip("/") if base else None
        self.client = httpx.AsyncClient(timeout=60, headers={"X-Engine-Key": settings.api_key}) if base else None

    async def send(self, path: str, payload: dict):
        if not self.client:
            return
        for attempt in range(3):
            try:
                r = await self.client.post(f"{self.base}/{path}", json=payload)
                r.raise_for_status()
                return
            except httpx.HTTPError:
                if attempt == 2:
                    raise
                await asyncio.sleep(2 ** attempt)

    async def close(self):
        if self.client:
            await self.client.aclose()


async def _fetch(client: httpx.AsyncClient, url: str):
    t0 = time.perf_counter()
    try:
        r = await client.get(url, follow_redirects=True)
        return r, int((time.perf_counter() - t0) * 1000), None
    except httpx.HTTPError as e:
        return None, int((time.perf_counter() - t0) * 1000), str(e)


async def _text_or_none(client, url):
    try:
        r = await client.get(url, follow_redirects=True)
        return r.text if r.status_code == 200 else None
    except httpx.HTTPError:
        return None


async def audit_url(url: str) -> dict:
    """Fast single-page audit (free public checker)."""
    async with httpx.AsyncClient(headers={"User-Agent": settings.user_agent}, timeout=settings.timeout) as client:
        r, ms, err = await _fetch(client, url)
        if err:
            return {"url": url, "error": err}
        page = analyze_page(str(r.url), r.status_code, dict(r.headers), r.text, ms)
        page.pop("body_text", None)
        return page


def _redirect_page(url: str, status: int, target: str, depth, ms: int) -> dict:
    return {
        "url": url, "status": status, "redirect": True, "final_url": target, "depth": depth, "load_ms": ms,
        "indexable": False, "title": None, "description": None, "canonical": None, "h1": [], "headings": [],
        "word_count": 0, "images": [], "links": [], "internal_links": [], "external_links": [],
        "schema_types": [], "ai_signals": {}, "scores": {"seo": None, "ai": None},
        "issues": [{"area": "seo", "severity": "notice", "code": "redirect",
                    "message": f"Redirects ({status}) to {target}. Update internal links to point to the final URL."}],
    }


def _crawlable(link: str, host: str) -> bool:
    p = urlparse(link)
    return p.scheme in ("http", "https") and p.netloc == host and not p.path.lower().endswith(SKIP_EXT)


async def crawl_site(start_url: str, max_pages: int = 100, callback_base: str | None = None, progress=None) -> dict:
    if "://" not in start_url:
        start_url = "https://" + start_url
    root = urlparse(start_url)
    base = f"{root.scheme}://{root.netloc}"
    cb = Callback(callback_base)

    try:
        limits = httpx.Limits(max_connections=settings.concurrency)
        async with httpx.AsyncClient(headers={"User-Agent": settings.user_agent},
                                     timeout=settings.timeout, limits=limits) as client:
            robots_txt = await _text_or_none(client, base + "/robots.txt")
            rp = RobotFileParser()
            rp.parse((robots_txt or "").splitlines())

            sitemap_urls = rp.site_maps() or [base + "/sitemap.xml"]
            sitemap_found = any([await _text_or_none(client, s) for s in sitemap_urls[:3]])
            llms_found = (await _text_or_none(client, base + "/llms.txt")) is not None

            depth_of = {start_url.rstrip("/"): 0}
            queue = [start_url.rstrip("/")]
            pages: list[dict] = []
            pending: list[dict] = []
            link_status: dict[str, int] = {}
            sem = asyncio.Semaphore(settings.concurrency)

            async def worker(url):
                async with sem:
                    if not rp.can_fetch(settings.user_agent, url):
                        return None
                    r, ms, err = await _fetch(client, url)
                    link_status[url] = r.status_code if r is not None else 0
                    if err or r is None:
                        return None
                    depth = depth_of.get(url)
                    final = str(r.url).rstrip("/")
                    if r.history and final != url:
                        # Record the redirect itself; crawl its target as its own page.
                        if final not in depth_of and _crawlable(final, root.netloc):
                            depth_of[final] = depth
                            queue.append(final)
                        return _redirect_page(url, r.history[0].status_code, final, depth, ms)
                    if "text/html" not in r.headers.get("content-type", ""):
                        return None
                    page = analyze_page(url, r.status_code, dict(r.headers), r.text, ms)
                    page["depth"] = depth
                    return page

            while queue and len(pages) < max_pages:
                batch, queue = queue[: settings.concurrency], queue[settings.concurrency:]
                for page in await asyncio.gather(*(worker(u) for u in batch)):
                    if not page:
                        continue
                    pages.append(page)
                    pending.append(page)
                    for link in page["internal_links"]:
                        if link not in depth_of and _crawlable(link, root.netloc) and len(depth_of) < max_pages * 3:
                            depth_of[link] = (page["depth"] or 0) + 1
                            queue.append(link)

                if len(pending) >= BATCH_SIZE:
                    await cb.send("pages", {"pages": pending})
                    for p in pending:
                        p.pop("body_text", None)
                    pending = []
                if progress:
                    progress(len(pages), max_pages)

            if pending:
                await cb.send("pages", {"pages": pending})
                for p in pending:
                    p.pop("body_text", None)

            # Status of links found but not crawled (external + beyond the limit), capped
            unchecked = list({l for p in pages for l in p["internal_links"] + p["external_links"]} - set(link_status))
            unchecked = unchecked[: settings.max_link_checks]

            async def head(u):
                async with sem:
                    try:
                        r = await client.head(u, follow_redirects=True)
                        if r.status_code in (403, 405):  # some servers reject HEAD
                            r = await client.get(u, follow_redirects=True)
                        link_status[u] = r.status_code
                    except httpx.HTTPError:
                        link_status[u] = 0
            await asyncio.gather(*(head(u) for u in unchecked))

            broken = [
                {"from": p["url"], "to": l, "status": link_status[l]}
                for p in pages for l in p["internal_links"] + p["external_links"]
                if l in link_status and (link_status[l] == 0 or link_status[l] >= 400)
            ]

        site = analyze_site(pages, robots_txt, sitemap_found, llms_found, broken)
        site["link_status"] = {u: s for u, s in link_status.items() if s == 0 or s >= 300}
        await cb.send("finish", {"site": site})
        return {"site": site, "pages": pages}
    except Exception as e:  # report the failure to Laravel, then re-raise for Celery
        try:
            await cb.send("fail", {"error": str(e)[:2000]})
        except Exception:
            pass
        raise
    finally:
        await cb.close()
