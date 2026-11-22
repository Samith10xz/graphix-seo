<?php

namespace Tests\Unit;

use App\Modules\Keywords\Services\KeywordUsageAnalyzer;
use PHPUnit\Framework\TestCase;

class KeywordUsageAnalyzerTest extends TestCase
{
    public function test_weights_add_up_to_100(): void
    {
        $this->assertSame(100, array_sum(KeywordUsageAnalyzer::WEIGHTS));
    }

    public function test_perfect_page_scores_100(): void
    {
        $body = 'Send parcel to UK from Colombo with tracking. '.str_repeat('Fast reliable delivery for families and businesses. ', 30);
        $result = (new KeywordUsageAnalyzer)->analyze('send parcel to UK', [
            'url' => 'https://parcel.lk/send-parcel-to-uk/',
            'title' => 'Send Parcel to UK from Sri Lanka | Parcel.LK',
            'meta_description' => 'Send parcel to UK with door pickup.',
            'h1' => ['Send Parcel to UK'],
            'headings' => [['level' => 1, 'text' => 'Send Parcel to UK'], ['level' => 2, 'text' => 'How long does it take to send parcel to UK?']],
            'body_text' => $body,
            'images' => [['alt' => 'Send parcel to UK box']],
            'anchors' => ['send parcel to UK'],
        ]);

        $this->assertSame(100, $result['score']);
        $this->assertSame(1, $result['checks']['title_position']);
    }

    public function test_missing_keyword_scores_zero_and_slug_matching_uses_hyphens(): void
    {
        $analyzer = new KeywordUsageAnalyzer;

        $none = $analyzer->analyze('international courier', ['url' => 'https://parcel.lk/rates/', 'title' => 'Rates', 'body_text' => 'Domestic prices']);
        $this->assertSame(0, $none['score']);

        $slug = $analyzer->analyze('cross border', ['url' => 'https://parcel.lk/cross-border/']);
        $this->assertTrue($slug['checks']['url']);
    }

    public function test_partial_word_does_not_match(): void
    {
        $result = (new KeywordUsageAnalyzer)->analyze('rate', ['url' => 'https://x.lk/', 'title' => 'Our rates and pricing']);
        $this->assertFalse($result['checks']['title']);
    }
}
