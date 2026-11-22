<?php

namespace App\Modules\Data;

use App\Contracts\KeywordDataProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * DataForSEO adapter. Every result is cached (config services.dataforseo.cache_days)
 * so repeated lookups for the same domain or keywords cost nothing.
 */
class DataForSeoClient implements KeywordDataProvider
{
    /** ISO country code → DataForSEO location code. Extend as markets are added. */
    private const LOCATIONS = [
        'LK' => 2144, 'GB' => 2826, 'US' => 2840, 'AU' => 2036, 'CA' => 2124,
        'AE' => 2784, 'IN' => 2356, 'SG' => 2702, 'MY' => 2458, 'QA' => 2634, 'SA' => 2682, 'NZ' => 2554,
    ];

    public function competitors(string $domain, string $country, string $language, int $limit = 10): array
    {
        $result = $this->post('dataforseo_labs/google/competitors_domain/live', [[
            'target' => $domain,
            'location_code' => $this->location($country),
            'language_code' => $language,
            'limit' => $limit,
            'exclude_top_domains' => true,
        ]]);

        return collect($result['items'] ?? [])->map(fn ($item) => [
            'domain' => $item['domain'],
            'shared_keywords' => (int) ($item['intersections'] ?? 0),
            'organic_keywords' => $item['full_domain_metrics']['organic']['count'] ?? null,
            'estimated_traffic' => isset($item['full_domain_metrics']['organic']['etv'])
                ? (int) round($item['full_domain_metrics']['organic']['etv']) : null,
        ])->values()->all();
    }

    public function keywordMetrics(array $keywords, string $country, string $language): array
    {
        $result = $this->post('dataforseo_labs/google/keyword_overview/live', [[
            'keywords' => array_values(array_unique($keywords)),
            'location_code' => $this->location($country),
            'language_code' => $language,
        ]]);

        $out = [];
        foreach ($result['items'] ?? [] as $item) {
            $out[$item['keyword']] = [
                'volume' => $item['keyword_info']['search_volume'] ?? null,
                'difficulty' => $item['keyword_properties']['keyword_difficulty'] ?? null,
                'cpc' => $item['keyword_info']['cpc'] ?? null,
                'intent' => $item['search_intent_info']['main_intent'] ?? null,
            ];
        }

        return $out;
    }

    private function location(string $country): int
    {
        return self::LOCATIONS[strtoupper($country)]
            ?? throw new RuntimeException("Country {$country} is not mapped to a DataForSEO location yet");
    }

    /** POST one task, return its first result (cached). */
    private function post(string $endpoint, array $payload): array
    {
        $key = 'dfs:'.sha1($endpoint.json_encode($payload));

        return Cache::remember($key, now()->addDays(config('services.dataforseo.cache_days', 7)), function () use ($endpoint, $payload) {
            $login = config('services.dataforseo.login');
            if (! $login) {
                throw new RuntimeException('DataForSEO login is not set');
            }

            $response = Http::withBasicAuth($login, config('services.dataforseo.password'))
                ->timeout(90)
                ->post(config('services.dataforseo.base_url').'/'.$endpoint, $payload)
                ->throw();

            $task = $response->json('tasks.0');
            if (($task['status_code'] ?? 0) !== 20000) {
                throw new RuntimeException('DataForSEO: '.($task['status_message'] ?? 'unknown error'));
            }

            return $task['result'][0] ?? [];
        });
    }
}
