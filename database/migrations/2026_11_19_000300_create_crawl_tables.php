<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued'); // queued | running | done | failed
            $table->string('engine_job_id')->nullable();
            $table->unsignedInteger('max_pages')->default(500);
            $table->unsignedInteger('pages_crawled')->default(0);
            $table->unsignedTinyInteger('seo_score')->nullable();
            $table->unsignedTinyInteger('ai_score')->nullable();
            $table->json('summary')->nullable(); // site-level checks: robots, sitemap, llms.txt, blocked AI bots
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crawl_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->char('url_hash', 40)->index(); // sha1(url) for joins and lookups
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->boolean('indexable')->default(true);
            $table->string('canonical', 2048)->nullable();
            $table->string('title', 1024)->nullable();
            $table->text('meta_description')->nullable();
            $table->json('h1')->nullable();
            $table->json('headings')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedSmallInteger('depth')->nullable();
            $table->unsignedInteger('inlinks')->default(0);
            $table->unsignedInteger('outlinks')->default(0);
            $table->unsignedInteger('load_ms')->nullable();
            $table->decimal('size_kb', 8, 1)->nullable();
            $table->json('schema_types')->nullable();
            $table->json('images')->nullable();     // [{src, alt}]
            $table->json('ai_signals')->nullable();
            $table->mediumText('body_text')->nullable(); // for keyword usage checks
            $table->unsignedTinyInteger('seo_score')->nullable();
            $table->unsignedTinyInteger('ai_score')->nullable();
            $table->timestamps();
            $table->unique(['crawl_id', 'url_hash']);
        });

        Schema::create('links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crawl_id')->constrained()->cascadeOnDelete();
            $table->char('from_hash', 40)->index();
            $table->string('to_url', 2048);
            $table->char('to_hash', 40)->index();
            $table->string('anchor', 512)->nullable();
            $table->boolean('internal')->default(true);
            $table->boolean('nofollow')->default(false);
            $table->unsignedSmallInteger('status_code')->nullable();
        });

        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crawl_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->nullable()->constrained()->cascadeOnDelete(); // null = site-wide
            $table->string('code')->index();
            $table->string('area');     // seo | ai | speed
            $table->string('severity'); // critical | warning | notice
            $table->string('message', 1024);
            $table->timestamp('resolved_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues');
        Schema::dropIfExists('links');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('crawls');
    }
};
