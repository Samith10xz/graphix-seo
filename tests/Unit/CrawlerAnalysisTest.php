<?php

namespace Tests\Unit;

use App\Modules\Crawler\Analysis\PageAnalyzer;
use App\Modules\Crawler\Analysis\RobotsTxt;
use App\Modules\Crawler\Analysis\SiteAnalyzer;
use App\Modules\Crawler\Analysis\Url;
use PHPUnit\Framework\TestCase;

class CrawlerAnalysisTest extends TestCase
{
    private const WEAK = '<html><head><title>Hi</title></head><body><p>short</p><img src=a.png></body></html>';

    private function good(): string
    {
        $answer = str_repeat('Courier delivery to the UK usually takes three to five working days from Colombo. ', 3);
        $filler = str_repeat('Reliable shipping for families and businesses across the world. ', 40);

        return '<html lang=en><head><title>International Courier Service from Sri Lanka | Parcel.LK</title>'
            .'<meta name=description content="Send parcels from Sri Lanka to 200+ countries with tracking, door pickup and fast customs clearance. Get an instant quote today.">'
            .'<meta name=viewport content="width=device-width"><link rel=canonical href="https://parcel.lk/">'
            .'<meta property=og:title content=x><meta property=og:image content=y><meta name=author content=Team>'
            .'<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"Organization","name":"Parcel.LK"},{"@type":"FAQPage","datePublished":"2026-01-01"}]}</script>'
            ."</head><body><h1>International courier</h1><h2>How long does delivery to the UK take?</h2><p>{$answer}</p>"
            .'<ul><li>a</li></ul><a href="/rates">Courier rates</a><a href="https://dhl.com" rel="nofollow">DHL</a>'
            .'<img src="/box.png" alt="International courier box">'
            ."<p>{$filler}</p></body></html>";
    }

    public function test_weak_page_has_core_issues(): void
    {
        $r = (new PageAnalyzer)->analyze('https://example.com/x', 200, [], self::WEAK, 1500);
        $codes = array_column($r['issues'], 'code');

        $this->assertContains('h1_missing', $codes);
        $this->assertContains('viewport_missing', $codes);
        $this->assertSame(['seo' => 32, 'ai' => 70], $r['scores']);
    }

    public function test_good_page_scores_full_with_links_and_schema(): void
    {
        $r = (new PageAnalyzer)->analyze('https://parcel.lk/', 200, [], $this->good(), 400);

        $this->assertSame([], $r['issues']);
        $this->assertSame(['FAQPage', 'Organization'], $r['schema_types']);
        $byUrl = array_column($r['links'], null, 'url');
        $this->assertSame('Courier rates', $byUrl['https://parcel.lk/rates']['anchor']);
        $this->assertTrue($byUrl['https://dhl.com']['nofollow']);
        $this->assertStringNotContainsString('schema.org', $r['body_text']);
    }

    public function test_noindex_header(): void
    {
        $r = (new PageAnalyzer)->analyze('https://parcel.lk/', 200, ['X-Robots-Tag' => 'noindex'], $this->good(), 400);
        $this->assertFalse($r['indexable']);
    }

    public function test_url_resolution(): void
    {
        $this->assertSame('https://parcel.lk/rates/?a=1#x', Url::resolve('https://parcel.lk/services/', '../rates/?a=1#x'));
        $this->assertSame('https://cdn.x.com/a', Url::resolve('https://parcel.lk/', '//cdn.x.com/a'));
        $this->assertSame('https://parcel.lk/rates', Url::normalize('https://parcel.lk/rates/#top'));
    }

    public function test_robots_rules(): void
    {
        $robots = new RobotsTxt("User-agent: *\nDisallow: /admin\nAllow: /admin/public\n\nUser-agent: GPTBot\nDisallow: /\n\nSitemap: https://x.lk/sitemap.xml");

        $this->assertFalse($robots->allows('GraphixSEOBot/1.0', 'https://x.lk/admin/users'));
        $this->assertTrue($robots->allows('GraphixSEOBot/1.0', 'https://x.lk/admin/public/page'));
        $this->assertFalse($robots->allows('GPTBot', 'https://x.lk/'));
        $this->assertTrue($robots->allows('ClaudeBot', 'https://x.lk/'));
        $this->assertSame(['https://x.lk/sitemap.xml'], $robots->sitemaps());
    }

    public function test_site_checks(): void
    {
        $good = (new PageAnalyzer)->analyze('https://parcel.lk/', 200, [], $this->good(), 400);
        $redirect = ['url' => 'https://parcel.lk/old', 'redirect' => true, 'issues' => [], 'scores' => ['seo' => null, 'ai' => null], 'indexable' => false];

        $s = (new SiteAnalyzer)->analyze([$good, $redirect], "User-agent: GPTBot\nDisallow: /\n", false, false, [['from' => 'x', 'to' => 'y', 'status' => 404]]);

        $this->assertSame(['GPTBot'], $s['blocked_ai_bots']);
        $this->assertSame(1, $s['pages_crawled']);
        $this->assertSame(1, $s['redirects']);
        $this->assertEqualsCanonicalizing(
            ['sitemap_missing', 'llms_txt_missing', 'ai_bots_blocked', 'broken_links'],
            array_column($s['issues'], 'code'),
        );
    }
}
