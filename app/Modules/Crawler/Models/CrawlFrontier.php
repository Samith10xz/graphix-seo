<?php

namespace App\Modules\Crawler\Models;

use Illuminate\Database\Eloquent\Model;

class CrawlFrontier extends Model
{
    protected $table = 'crawl_frontier';

    public $timestamps = false;

    protected $fillable = ['crawl_id', 'url', 'url_hash', 'depth', 'status'];
}
