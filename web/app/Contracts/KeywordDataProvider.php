<?php

namespace App\Contracts;

interface KeywordDataProvider
{
    /**
     * Domains that compete with $domain in Google for the given location.
     *
     * @return list<array{domain:string, shared_keywords:int, organic_keywords:?int, estimated_traffic:?int}>
     */
    public function competitors(string $domain, string $country, string $language, int $limit = 10): array;

    /**
     * Search volume, difficulty, CPC and intent for keywords.
     *
     * @param  list<string>  $keywords
     * @return array<string, array{volume:?int, difficulty:?int, cpc:?float, intent:?string}>
     */
    public function keywordMetrics(array $keywords, string $country, string $language): array;
}
