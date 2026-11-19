import os


class Settings:
    api_key: str = os.getenv("ENGINE_API_KEY", "")
    redis_url: str = os.getenv("REDIS_URL", "redis://redis:6379/1")
    user_agent: str = os.getenv("CRAWLER_UA", "GraphixSEOBot/1.0 (+https://seo.graphixdesign.agency/bot)")
    timeout: float = float(os.getenv("CRAWL_TIMEOUT", "15"))
    concurrency: int = int(os.getenv("CRAWL_CONCURRENCY", "8"))
    max_pages_limit: int = int(os.getenv("MAX_PAGES_LIMIT", "5000"))
    max_link_checks: int = int(os.getenv("MAX_LINK_CHECKS", "300"))
    pagespeed_key: str = os.getenv("PAGESPEED_API_KEY", "")


settings = Settings()
