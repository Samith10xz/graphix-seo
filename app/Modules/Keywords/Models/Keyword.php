<?php

namespace App\Modules\Keywords\Models;

use App\Modules\Projects\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Keyword extends Model
{
    protected $fillable = [
        'project_id', 'keyword', 'volume', 'volume_range', 'difficulty', 'cpc', 'intent', 'cluster',
        'target_url', 'page_category', 'source', 'track_rank',
    ];

    protected function casts(): array
    {
        return ['track_rank' => 'boolean', 'cpc' => 'decimal:2'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function usage(): HasMany
    {
        return $this->hasMany(KeywordPageUsage::class);
    }

    public function rankChecks(): HasMany
    {
        return $this->hasMany(RankCheck::class)->orderByDesc('checked_on');
    }
}
