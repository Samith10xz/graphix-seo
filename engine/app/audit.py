"""
Page-level SEO + AEO/GEO checks.

analyze_page() is pure: give it the fetched URL, status, headers, HTML and
load time, and it returns metrics, issues and two scores (SEO and AI-readiness).
No network calls here, so it is easy to test and reuse.
"""
from __future__ import annotations

import json
import re
from urllib.parse import urljoin, urlparse, urldefrag

from bs4 import BeautifulSoup

# Severity -> points deducted from a score of 100
WEIGHTS = {"critical": 15, "warning": 6, "notice": 2}

QUESTION_RE = re.compile(
    r"^(what|why|how|when|where|who|which|can|does|do|is|are|should|will)\b|\?$",
    re.I,
)


def _issue(issues, area, severity, code, message):
    issues.append({"area": area, "severity": severity, "code": code, "message": message})


def _norm(url: str) -> str:
    url, _ = urldefrag(url)
    return url.rstrip("/") or url


def _words(text: str) -> list[str]:
    return re.findall(r"[\w'-]+", text, flags=re.UNICODE)


def _jsonld(soup) -> tuple[list[dict], int]:
    """Return all JSON-LD objects (flattened @graph) and the count of invalid blocks."""
    items, errors = [], 0
    for tag in soup.find_all("script", type="application/ld+json"):
        try:
            data = json.loads(tag.string or "")
        except (json.JSONDecodeError, TypeError):
            errors += 1
            continue
        stack = data if isinstance(data, list) else [data]
        while stack:
            obj = stack.pop()
            if isinstance(obj, dict):
                items.append(obj)
                if isinstance(obj.get("@graph"), list):
                    stack.extend(obj["@graph"])
    return items, errors


def _types(items) -> set[str]:
    out = set()
    for obj in items:
        t = obj.get("@type")
        for v in (t if isinstance(t, list) else [t]):
            if isinstance(v, str):
                out.add(v)
    return out


def analyze_page(url: str, status: int, headers: dict, html: str, load_ms: int = 0) -> dict:
    issues: list[dict] = []
    headers = {k.lower(): v for k, v in (headers or {}).items()}
    soup = BeautifulSoup(html or "", "lxml")
    host = urlparse(url).netloc

    # ---------- HTTP ----------
    if status >= 400:
        _issue(issues, "seo", "critical", "http_error", f"Page returns HTTP {status}.")
    if load_ms > 3000:
        _issue(issues, "seo", "warning", "slow_response", f"Server responded in {load_ms} ms (aim for under 1000 ms).")
    elif load_ms > 1000:
        _issue(issues, "seo", "notice", "slowish_response", f"Server responded in {load_ms} ms.")
    size_kb = round(len((html or "").encode()) / 1024, 1)
    if size_kb > 2048:
        _issue(issues, "seo", "warning", "large_html", f"HTML is {size_kb} KB; keep pages lean.")
    x_robots = headers.get("x-robots-tag", "")

    # ---------- Head ----------
    title = (soup.title.string or "").strip() if soup.title and soup.title.string else ""
    if not title:
        _issue(issues, "seo", "critical", "title_missing", "Missing <title> tag.")
    elif len(title) < 30:
        _issue(issues, "seo", "warning", "title_short", f"Title is {len(title)} characters (aim for 30–60).")
    elif len(title) > 60:
        _issue(issues, "seo", "warning", "title_long", f"Title is {len(title)} characters and may be cut off in Google (aim for 30–60).")

    md = soup.find("meta", attrs={"name": re.compile("^description$", re.I)})
    description = (md.get("content") or "").strip() if md else ""
    if not description:
        _issue(issues, "seo", "warning", "description_missing", "Missing meta description.")
    elif len(description) < 70:
        _issue(issues, "seo", "notice", "description_short", f"Meta description is {len(description)} characters (aim for 70–160).")
    elif len(description) > 160:
        _issue(issues, "seo", "notice", "description_long", f"Meta description is {len(description)} characters (aim for 70–160).")

    mr = soup.find("meta", attrs={"name": re.compile("^robots$", re.I)})
    robots_meta = (mr.get("content") or "").lower() if mr else ""
    noindex = "noindex" in robots_meta or "noindex" in x_robots.lower()
    if noindex:
        _issue(issues, "seo", "critical", "noindex", "Page is set to noindex — Google will not show it.")

    can = soup.find("link", rel=lambda v: v and "canonical" in [x.lower() for x in (v if isinstance(v, list) else [v])])
    canonical = urljoin(url, can.get("href")) if can and can.get("href") else ""
    if not canonical:
        _issue(issues, "seo", "warning", "canonical_missing", "No canonical tag.")
    elif _norm(canonical) != _norm(url):
        _issue(issues, "seo", "notice", "canonical_other", f"Canonical points to another URL: {canonical}")

    html_tag = soup.find("html")
    lang = html_tag.get("lang", "") if html_tag else ""
    if not lang:
        _issue(issues, "seo", "notice", "lang_missing", "<html> has no lang attribute.")
    if not soup.find("meta", attrs={"name": re.compile("^viewport$", re.I)}):
        _issue(issues, "seo", "critical", "viewport_missing", "No viewport meta tag — page is not mobile-friendly.")

    og = {m.get("property"): m.get("content") for m in soup.find_all("meta", property=re.compile("^og:"))}
    if not og.get("og:title") or not og.get("og:image"):
        _issue(issues, "seo", "notice", "og_incomplete", "Open Graph title/image missing (affects social shares).")
    hreflangs = [l.get("hreflang") for l in soup.find_all("link", hreflang=True)]

    # ---------- Content ----------
    body = soup.body or soup
    for t in body(["script", "style", "noscript", "template", "svg"]):
        t.decompose()
    text = body.get_text(" ", strip=True)
    words = _words(text)
    word_count = len(words)
    if word_count < 300:
        _issue(issues, "seo", "warning", "thin_content", f"Only {word_count} words of content (thin page).")

    h1s = [h.get_text(" ", strip=True) for h in soup.find_all("h1")]
    if not h1s:
        _issue(issues, "seo", "critical", "h1_missing", "No H1 heading.")
    elif len(h1s) > 1:
        _issue(issues, "seo", "notice", "h1_multiple", f"{len(h1s)} H1 headings found; use one.")

    headings = [(int(h.name[1]), h.get_text(" ", strip=True)) for h in soup.find_all(re.compile("^h[1-6]$"))]
    prev = 0
    for level, _ in headings:
        if prev and level > prev + 1:
            _issue(issues, "seo", "notice", "heading_skip", f"Heading levels skip from H{prev} to H{level}.")
            break
        prev = level

    imgs = soup.find_all("img")
    images = [{"src": urljoin(url, i.get("src", "")), "alt": (i.get("alt") or "").strip()} for i in imgs[:200]]
    no_alt = [i.get("src", "") for i in imgs if not (i.get("alt") or "").strip()]
    if no_alt:
        _issue(issues, "seo", "warning", "img_alt_missing", f"{len(no_alt)} of {len(imgs)} images have no alt text.")

    # ---------- Links ----------
    internal, external, links, seen_links = set(), set(), [], set()
    for a in soup.find_all("a", href=True):
        href = a["href"].strip()
        if href.startswith(("mailto:", "tel:", "javascript:", "#")):
            continue
        absu = _norm(urljoin(url, href))
        if not absu.startswith("http"):
            continue
        is_internal = urlparse(absu).netloc == host
        (internal if is_internal else external).add(absu)
        anchor = a.get_text(" ", strip=True)[:300]
        if (absu, anchor) not in seen_links:
            seen_links.add((absu, anchor))
            rel = [r.lower() for r in (a.get("rel") or [])]
            links.append({"url": absu, "anchor": anchor, "internal": is_internal, "nofollow": "nofollow" in rel})
    if not internal:
        _issue(issues, "seo", "notice", "no_internal_links", "No internal links on this page.")

    # ---------- Structured data ----------
    ld, ld_errors = _jsonld(soup)
    types = _types(ld)
    if ld_errors:
        _issue(issues, "seo", "warning", "schema_invalid", f"{ld_errors} JSON-LD block(s) contain invalid JSON.")
    if not ld:
        _issue(issues, "ai", "warning", "schema_missing", "No structured data (JSON-LD). AI engines and rich results rely on it.")

    # ---------- AEO / GEO (AI-readiness) ----------
    question_heads = [t for lvl, t in headings if lvl >= 2 and QUESTION_RE.search(t)]
    if not question_heads:
        _issue(issues, "ai", "notice", "no_question_headings",
               "No question-style headings (What/How/Why…). These help pages get quoted in AI answers and featured snippets.")

    # Answer-first: paragraph right after a question heading should be a direct 25–80 word answer
    weak_answers = 0
    for h in soup.find_all(re.compile("^h[2-4]$")):
        if QUESTION_RE.search(h.get_text(" ", strip=True)):
            p = h.find_next(["p", "ul", "ol"])
            n = len(_words(p.get_text(" ", strip=True))) if p else 0
            if not (25 <= n <= 80):
                weak_answers += 1
    if question_heads and weak_answers:
        _issue(issues, "ai", "notice", "answers_not_concise",
               f"{weak_answers} question heading(s) aren't followed by a direct 25–80 word answer.")

    if question_heads and "FAQPage" not in types:
        _issue(issues, "ai", "notice", "faq_schema_missing", "Page has Q&A content but no FAQPage schema.")

    has_lists = bool(soup.find(["ul", "ol"]))
    has_tables = bool(soup.find("table"))
    if word_count >= 600 and not (has_lists or has_tables):
        _issue(issues, "ai", "notice", "no_structure", "Long page with no lists or tables; structured content is easier for AI to extract.")

    author = bool(soup.find("meta", attrs={"name": re.compile("^author$", re.I)})) or any("author" in o for o in ld)
    published = any(k in o for o in ld for k in ("datePublished", "dateModified")) or bool(
        soup.find("meta", property=re.compile("article:(published|modified)_time")) or soup.find("time"))
    if not author:
        _issue(issues, "ai", "notice", "no_author", "No author signal (E-E-A-T). Add an author byline or Article.author schema.")
    if not published:
        _issue(issues, "ai", "notice", "no_date", "No published/updated date. AI engines prefer fresh, dated content.")
    if not ({"Organization", "LocalBusiness", "Person", "WebSite"} & types):
        _issue(issues, "ai", "notice", "no_entity_schema", "No Organization/LocalBusiness schema to define your brand entity.")

    sentences = [s for s in re.split(r"[.!?]+\s", text) if len(_words(s)) > 2]
    avg_sentence = round(word_count / len(sentences), 1) if sentences else 0
    if avg_sentence > 25:
        _issue(issues, "ai", "notice", "long_sentences", f"Average sentence is {avg_sentence} words; shorter sentences are easier to quote.")

    stats = len(re.findall(r"\b\d+(?:[.,]\d+)?\s?(?:%|percent|million|billion|k)\b", text, re.I))

    # ---------- Scores ----------
    def score(area):
        # AI-readiness has fewer, more decisive checks, so its notices count more
        w = dict(WEIGHTS, notice=6) if area == "ai" else WEIGHTS
        return max(0, 100 - sum(w[i["severity"]] for i in issues if i["area"] == area))

    return {
        "url": url,
        "status": status,
        "load_ms": load_ms,
        "size_kb": size_kb,
        "indexable": status < 400 and not noindex,
        "title": title,
        "description": description,
        "canonical": canonical,
        "lang": lang,
        "h1": h1s,
        "headings": [{"level": l, "text": t} for l, t in headings[:50]],
        "word_count": word_count,
        "images": images,
        "image_count": len(imgs),
        "images_missing_alt": no_alt[:20],
        "internal_links": sorted(internal),
        "external_links": sorted(external),
        "links": links,
        "body_text": text[:100_000],
        "schema_types": sorted(types),
        "hreflang": hreflangs,
        "open_graph": og,
        "ai_signals": {
            "question_headings": question_heads[:20],
            "faq_schema": "FAQPage" in types,
            "lists": has_lists,
            "tables": has_tables,
            "author": author,
            "dated": published,
            "stats_mentions": stats,
            "avg_sentence_words": avg_sentence,
        },
        "scores": {"seo": score("seo"), "ai": score("ai")},
        "issues": issues,
    }


AI_BOTS = ["GPTBot", "OAI-SearchBot", "ChatGPT-User", "ClaudeBot", "PerplexityBot", "Google-Extended", "Bingbot"]


def analyze_site(pages: list[dict], robots_txt: str | None, sitemap_found: bool,
                 llms_txt_found: bool, broken: list[dict]) -> dict:
    """Cross-page checks: duplicates, broken links, robots/sitemap/llms.txt, AI bot access."""
    from urllib.robotparser import RobotFileParser

    issues: list[dict] = []
    if robots_txt is None:
        _issue(issues, "seo", "warning", "robots_missing", "No robots.txt file.")
    if not sitemap_found:
        _issue(issues, "seo", "warning", "sitemap_missing", "No XML sitemap found (checked robots.txt and /sitemap.xml).")
    if not llms_txt_found:
        _issue(issues, "ai", "notice", "llms_txt_missing", "No /llms.txt file to guide AI assistants to your key pages.")

    blocked_bots = []
    if robots_txt and pages:
        rp = RobotFileParser()
        rp.parse(robots_txt.splitlines())
        home = pages[0]["url"]
        blocked_bots = [b for b in AI_BOTS if not rp.can_fetch(b, home)]
        if blocked_bots:
            _issue(issues, "ai", "warning", "ai_bots_blocked",
                   f"robots.txt blocks AI crawlers: {', '.join(blocked_bots)}. You won't be cited by those engines.")

    def dupes(field):
        seen: dict[str, list[str]] = {}
        for p in pages:
            v = (p.get(field) or "").strip().lower()
            if v and p.get("indexable"):
                seen.setdefault(v, []).append(p["url"])
        return [{"value": k, "urls": v} for k, v in seen.items() if len(v) > 1]

    dup_titles, dup_desc = dupes("title"), dupes("description")
    if dup_titles:
        _issue(issues, "seo", "warning", "duplicate_titles", f"{len(dup_titles)} title(s) are used on more than one page.")
    if dup_desc:
        _issue(issues, "seo", "notice", "duplicate_descriptions", f"{len(dup_desc)} meta description(s) are duplicated.")
    if broken:
        _issue(issues, "seo", "critical", "broken_links", f"{len(broken)} broken link(s) found.")

    scored = [p for p in pages if not p.get("redirect")]
    n = len(scored) or 1
    totals = {"critical": 0, "warning": 0, "notice": 0}
    for p in pages:
        for i in p["issues"]:
            totals[i["severity"]] += 1
    for i in issues:
        totals[i["severity"]] += 1

    site_pen = lambda area: sum(WEIGHTS[i["severity"]] for i in issues if i["area"] == area)
    seo = round(sum(p["scores"]["seo"] for p in scored) / n) if scored else 0
    ai = round(sum(p["scores"]["ai"] for p in scored) / n) if scored else 0

    return {
        "pages_crawled": len(scored),
        "redirects": len(pages) - len(scored),
        "scores": {"seo": max(0, seo - site_pen("seo")), "ai": max(0, ai - site_pen("ai"))},
        "issue_totals": totals,
        "blocked_ai_bots": blocked_bots,
        "duplicate_titles": dup_titles,
        "duplicate_descriptions": dup_desc,
        "broken_links": broken,
        "issues": issues,
    }
