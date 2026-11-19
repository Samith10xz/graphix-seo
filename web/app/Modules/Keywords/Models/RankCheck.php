<?php

namespace App\Modules\Keywords\Models;

use Illuminate\Database\Eloquent\Model;

class RankCheck extends Model
{
    protected $fillable = ['keyword_id', 'checked_on', 'device', 'position', 'ranking_url', 'serp_features'];

    protected function casts(): array
    {
        return ['checked_on' => 'date', 'serp_features' => 'array'];
    }
}
