<?php

namespace App\Modules\Crawler\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    protected $fillable = [
        'crawl_id', 'url', 'url_hash', 'status_code', 'indexable', 'canonical', 'title', 'meta_description',
        'h1', 'headings', 'word_count', 'depth', 'inlinks', 'outlinks', 'load_ms', 'size_kb',
        'schema_types', 'images', 'ai_signals', 'body_text', 'seo_score', 'ai_score',
    ];

    protected $hidden = ['body_text'];

    protected function casts(): array
    {
        return [
            'indexable' => 'boolean', 'h1' => 'array', 'headings' => 'array', 'schema_types' => 'array',
            'images' => 'array', 'ai_signals' => 'array',
        ];
    }

    public static function hashUrl(string $url): string
    {
        return sha1(rtrim($url, '/'));
    }

    public function crawl(): BelongsTo
    {
        return $this->belongsTo(Crawl::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }
}
