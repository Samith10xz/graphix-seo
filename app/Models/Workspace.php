<?php

namespace App\Models;

use App\Modules\Projects\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    protected $fillable = ['name', 'slug', 'plan', 'branding', 'features', 'limits'];

    protected function casts(): array
    {
        return ['branding' => 'array', 'features' => 'array', 'limits' => 'array'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')->withPivot('role')->withTimestamps();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** Feature switch: workspace override first, then plan default (on). */
    public function hasFeature(string $feature): bool
    {
        return (bool) ($this->features[$feature] ?? true);
    }

    public function limit(string $key, int $default): int
    {
        return (int) ($this->limits[$key] ?? $default);
    }
}
