<?php

namespace App\Modules\Crawler\Models;

use App\Modules\Projects\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Crawl extends Model
{
    protected $fillable = [
        'project_id', 'status', 'engine_job_id', 'max_pages', 'pages_crawled', 'seo_score', 'ai_score',
        'summary', 'error', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return ['summary' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
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
