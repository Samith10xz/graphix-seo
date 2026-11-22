<?php

namespace App\Modules\Plan\Models;

use App\Models\User;
use App\Modules\Projects\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectStep extends Model
{
    public const LOCKED = 'locked';
    public const READY = 'ready';
    public const GENERATING = 'generating';
    public const REVIEW = 'review';
    public const APPROVED = 'approved';

    protected $fillable = ['project_id', 'key', 'name', 'position', 'enabled', 'custom', 'status', 'data', 'notes', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['data' => 'array', 'enabled' => 'boolean', 'custom' => 'boolean', 'approved_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->status === self::APPROVED;
    }

    /** The config entry for this step (null for custom client-only steps). */
    public function definition(): ?array
    {
        return collect(config('seo_plan.steps'))->firstWhere('key', $this->key);
    }
}
