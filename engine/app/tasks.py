"""Background jobs (Celery + Redis): full-site crawls never block the API."""
import asyncio

from celery import Celery

from .config import settings
from .crawler import crawl_site

celery = Celery("seo_engine", broker=settings.redis_url, backend=settings.redis_url)
celery.conf.update(result_expires=60 * 60 * 24, task_track_started=True,
                   worker_prefetch_multiplier=1, task_time_limit=60 * 60)


@celery.task(bind=True, name="site_audit")
def site_audit(self, url: str, max_pages: int, callback_base: str | None = None):
    def progress(done, total):
        self.update_state(state="PROGRESS", meta={"crawled": done, "max_pages": total})

    result = asyncio.run(crawl_site(url, max_pages, callback_base, progress))
    # Laravel already has the pages; keep the Celery result small.
    return {"site": result["site"], "pages_crawled": len(result["pages"])}
