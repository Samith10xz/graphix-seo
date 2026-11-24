<?php

namespace App\Modules\Projects\Models;

use App\Models\Workspace;
use App\Modules\Competitors\Models\Competitor;
use App\Modules\Crawler\Models\Crawl;
use App\Modules\Keywords\Models\Keyword;
use App\Modules\Plan\Models\ProjectStep;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = ['workspace_id', 'name', 'url', 'domain', 'country', 'language', 'niche', 'settings', 'created_by'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ProjectStep::class)->orderBy('position');
    }

    public function competitors(): HasMany
    {
        return $this->hasMany(Competitor::class);
    }

    public function crawls(): HasMany
    {
        return $this->hasMany(Crawl::class);
    }

    public function latestCrawl(): HasOne
    {
        return $this->hasOne(Crawl::class)->latestOfMany();
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }
}
