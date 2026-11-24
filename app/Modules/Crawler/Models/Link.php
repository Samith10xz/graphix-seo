<?php

namespace App\Modules\Crawler\Models;

use Illuminate\Database\Eloquent\Model;

class Link extends Model
{
    public $timestamps = false;

    protected $fillable = ['crawl_id', 'from_hash', 'to_url', 'to_hash', 'anchor', 'internal', 'nofollow', 'status_code'];

    protected function casts(): array
    {
        return ['internal' => 'boolean', 'nofollow' => 'boolean'];
    }
}
