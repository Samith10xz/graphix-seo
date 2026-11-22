<?php

namespace App\Modules\Competitors\Models;

use App\Modules\Projects\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Competitor extends Model
{
    protected $fillable = [
        'project_id', 'domain', 'source', 'included', 'domain_authority', 'referring_domains',
        'organic_keywords', 'estimated_traffic', 'shared_keywords', 'metrics', 'metrics_at',
    ];

    protected function casts(): array
    {
        return ['included' => 'boolean', 'metrics' => 'array', 'metrics_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
