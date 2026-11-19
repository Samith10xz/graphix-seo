"""
Graphix SEO Engine API (internal; only Laravel calls it).

  GET  /health
  POST /v1/page-audit    {url}                                   instant single-page audit
  POST /v1/site-audits   {url, max_pages, crawl_id, callback_base} start a crawl → job_id
  GET  /v1/site-audits/{job_id}                                   status / progress
  POST /v1/pagespeed     {url, strategy}                          Core Web Vitals (Google PSI)

All /v1 routes need header  X-API-Key: <ENGINE_API_KEY>
"""
import httpx
from celery.result import AsyncResult
from fastapi import Depends, FastAPI, Header, HTTPException
from pydantic import BaseModel, Field, HttpUrl

from .config import settings
from .crawler import audit_url
from .tasks import celery, site_audit

app = FastAPI(title="Graphix SEO Engine", version="1.1")


def auth(x_api_key: str = Header(...)):
    if not settings.api_key or x_api_key != settings.api_key:
        raise HTTPException(401, "Invalid API key")


class PageReq(BaseModel):
    url: HttpUrl


class SiteReq(BaseModel):
    url: HttpUrl
    max_pages: int = Field(100, ge=1)
    crawl_id: int | None = None
    callback_base: str | None = None  # e.g. http://nginx/api/internal/crawls/42
    render_js: bool = False           # headless-browser rendering: planned, not yet active


class SpeedReq(BaseModel):
    url: HttpUrl
    strategy: str = Field("mobile", pattern="^(mobile|desktop)$")


@app.get("/health")
def health():
    return {"ok": True}


@app.post("/v1/page-audit", dependencies=[Depends(auth)])
async def page_audit(req: PageReq):
    return await audit_url(str(req.url))


@app.post("/v1/site-audits", dependencies=[Depends(auth)])
def start_site_audit(req: SiteReq):
    job = site_audit.delay(str(req.url), min(req.max_pages, settings.max_pages_limit), req.callback_base)
    return {"job_id": job.id, "status": "queued", "crawl_id": req.crawl_id}


@app.get("/v1/site-audits/{job_id}", dependencies=[Depends(auth)])
def get_site_audit(job_id: str):
    r = AsyncResult(job_id, app=celery)
    if r.state == "SUCCESS":
        return {"job_id": job_id, "status": "done", "result": r.result}
    if r.state == "FAILURE":
        return {"job_id": job_id, "status": "failed", "error": str(r.result)}
    if r.state == "PROGRESS":
        return {"job_id": job_id, "status": "running", "progress": r.info}
    return {"job_id": job_id, "status": r.state.lower()}


@app.post("/v1/pagespeed", dependencies=[Depends(auth)])
async def pagespeed(req: SpeedReq):
    """Lab and real-user Core Web Vitals from Google's PageSpeed Insights API."""
    params = {"url": str(req.url), "strategy": req.strategy,
              "category": ["performance", "seo", "accessibility", "best-practices"]}
    if settings.pagespeed_key:
        params["key"] = settings.pagespeed_key
    async with httpx.AsyncClient(timeout=90) as c:
        r = await c.get("https://www.googleapis.com/pagespeedonline/v5/runPagespeed", params=params)
    if r.status_code != 200:
        raise HTTPException(502, f"PageSpeed API error {r.status_code}")
    d = r.json()
    lh = d.get("lighthouseResult", {})
    audits = lh.get("audits", {})
    pick = lambda k: audits.get(k, {}).get("displayValue")
    field = d.get("loadingExperience", {}).get("metrics", {})
    return {
        "url": str(req.url),
        "strategy": req.strategy,
        "scores": {k: round((v.get("score") or 0) * 100) for k, v in lh.get("categories", {}).items()},
        "lab": {"LCP": pick("largest-contentful-paint"), "CLS": pick("cumulative-layout-shift"),
                "TBT": pick("total-blocking-time"), "FCP": pick("first-contentful-paint"),
                "SpeedIndex": pick("speed-index")},
        "field": {k: {"p75": v.get("percentile"), "rating": v.get("category")} for k, v in field.items()},
    }
