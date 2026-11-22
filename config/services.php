<?php

return [
    'engine' => [
        'url' => env('ENGINE_URL', 'http://engine:8000'),
        'key' => env('ENGINE_API_KEY'),
        'timeout' => 60,
        // where the engine sends crawl results (nginx on the Docker network)
        'callback_url' => env('ENGINE_CALLBACK_URL', 'http://nginx'),
    ],

    'dataforseo' => [
        'login' => env('DATAFORSEO_LOGIN'),
        'password' => env('DATAFORSEO_PASSWORD'),
        'base_url' => 'https://api.dataforseo.com/v3',
        // cache results this many days so repeat lookups cost nothing
        'cache_days' => 7,
    ],

    'pagespeed' => [
        'key' => env('PAGESPEED_API_KEY'),
    ],
];
