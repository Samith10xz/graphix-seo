<?php

return [
    /*
    | php    — crawler runs inside Laravel as small queued chunks (Hostinger / any PHP host)
    | python — crawler runs in the separate Python engine service (VPS with Docker)
    | Both produce identical page data, so switching needs no other change.
    */
    'engine' => env('CRAWL_ENGINE', 'php'),

    // PHP engine
    'chunk_size' => (int) env('CRAWL_CHUNK_SIZE', 20),   // pages fetched per queued job
    'concurrency' => (int) env('CRAWL_CONCURRENCY', 5),  // parallel requests inside a chunk
    'timeout' => (int) env('CRAWL_TIMEOUT', 15),         // seconds per request
    'max_pages' => (int) env('CRAWL_MAX_PAGES', 1000),   // hard ceiling per crawl
    'max_link_checks' => (int) env('CRAWL_MAX_LINK_CHECKS', 300),
    'user_agent' => env('CRAWLER_UA', 'GraphixSEOBot/1.0 (+https://seo.graphixdesign.agency/bot)'),

    'skip_extensions' => [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'zip', 'mp4', 'mp3', 'css', 'js', 'ico',
        'xml', 'doc', 'docx', 'xls', 'xlsx',
    ],
];
