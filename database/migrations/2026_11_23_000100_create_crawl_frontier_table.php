<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // URLs waiting to be crawled by the PHP engine (breadth-first by depth).
        Schema::create('crawl_frontier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crawl_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->char('url_hash', 40);
            $table->unsignedSmallInteger('depth')->default(0);
            $table->string('status', 12)->default('pending'); // pending | fetching | done | skipped
            $table->unique(['crawl_id', 'url_hash']);
            $table->index(['crawl_id', 'status', 'depth']);
        });

        Schema::table('crawls', function (Blueprint $table) {
            // engine working state: robots.txt, sitemap/llms.txt found, chunk counters
            $table->json('state')->nullable()->after('summary');
            $table->string('engine', 12)->default('php')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('crawls', function (Blueprint $table) {
            $table->dropColumn(['state', 'engine']);
        });
        Schema::dropIfExists('crawl_frontier');
    }
};
