<?php

namespace App\Modules\Crawler\Models;

use App\Modules\Projects\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Crawl extends Model
{
    protected $fillable = [
        'project_id', 'status', 'engine', 'engine_job_id', 'max_pages', 'pages_crawled', 'seo_score', 'ai_score',
        'summary', 'state', 'error', 'started_at', 'finished_at',
    ];

    protected $hidden = ['state'];

    protected function casts(): array
    {
        return ['summary' => 'array', 'state' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function frontier(): HasMany
    {
        return $this->hasMany(CrawlFrontier::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }
}
