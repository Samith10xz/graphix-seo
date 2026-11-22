<?php

namespace App\Contracts;

use App\Modules\Crawler\Models\Crawl;

/**
 * Starts a site crawl. Every engine delivers results through CrawlIngestService
 * (storePages in batches, then finish), so the rest of the app never knows which one ran.
 */
interface CrawlEngine
{
    public function start(Crawl $crawl): void;

    public function name(): string;
}
