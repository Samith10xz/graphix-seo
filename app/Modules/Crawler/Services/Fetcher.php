<?php

namespace App\Modules\Crawler\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/** HTTP for the PHP crawl engine: parallel page fetches with redirect tracking and timing. */
class Fetcher
{
    /**
     * Fetch many URLs in parallel.
     *
     * @param  list<string>  $urls
     * @return array<string, array{status:int, final_url:string, redirect_status:?int, headers:array, body:string, ms:int, error:?string}>
     */
    public function many(array $urls): array
    {
        $results = [];
        foreach (array_chunk($urls, max(1, (int) config('crawler.concurrency', 5))) as $batch) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn ($url) => $pool->as($url)->withOptions([
                    'allow_redirects' => ['max' => 5, 'track_redirects' => true],
                    'http_errors' => false,
                ])->withHeaders(['User-Agent' => config('crawler.user_agent')])
                    ->timeout(config('crawler.timeout', 15))
                    ->get($url),
                $batch,
            ));

            foreach ($batch as $url) {
                $results[$url] = $this->describe($responses[$url] ?? null);
            }
        }

        return $results;
    }

    /** Status code only (HEAD, falling back to GET when HEAD is refused). 0 = unreachable. */
    public function statuses(array $urls): array
    {
        $out = [];
        foreach (array_chunk($urls, max(1, (int) config('crawler.concurrency', 5))) as $batch) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn ($url) => $pool->as($url)->withOptions(['allow_redirects' => true, 'http_errors' => false])
                    ->withHeaders(['User-Agent' => config('crawler.user_agent')])
                    ->timeout(10)->head($url),
                $batch,
            ));
            foreach ($batch as $url) {
                $r = $responses[$url] ?? null;
                $out[$url] = $r instanceof Response ? $r->status() : 0;
            }
        }

        $retry = array_keys(array_filter($out, fn ($s) => in_array($s, [403, 405], true)));
        foreach ($this->many($retry) as $url => $r) {
            $out[$url] = $r['status'];
        }

        return $out;
    }

    public function textOrNull(string $url): ?string
    {
        try {
            $r = Http::withHeaders(['User-Agent' => config('crawler.user_agent')])->timeout(10)->get($url);

            return $r->successful() ? $r->body() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function describe(mixed $r): array
    {
        if (! $r instanceof Response) {
            return ['status' => 0, 'final_url' => '', 'redirect_status' => null, 'headers' => [], 'body' => '', 'ms' => 0,
                'error' => $r instanceof Throwable ? $r->getMessage() : 'No response'];
        }

        $psr = $r->toPsrResponse();
        $history = $psr->getHeader('X-Guzzle-Redirect-History');
        $statusHistory = $psr->getHeader('X-Guzzle-Redirect-Status-History');
        $stats = $r->handlerStats();

        return [
            'status' => $r->status(),
            'final_url' => $history ? (string) end($history) : (string) ($stats['url'] ?? ''),
            'redirect_status' => $statusHistory ? (int) $statusHistory[0] : null,
            'headers' => $r->headers(),
            'body' => $r->body(),
            'ms' => (int) round(($stats['total_time'] ?? 0) * 1000),
            'error' => null,
        ];
    }
}
