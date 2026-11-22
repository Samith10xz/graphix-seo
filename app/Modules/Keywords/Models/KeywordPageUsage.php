<?php

namespace App\Modules\Keywords\Models;

use App\Modules\Crawler\Models\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeywordPageUsage extends Model
{
    protected $table = 'keyword_page_usage';

    protected $fillable = ['keyword_id', 'page_id', 'score', 'checks', 'is_target'];

    protected function casts(): array
    {
        return ['checks' => 'array', 'is_target' => 'boolean'];
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
