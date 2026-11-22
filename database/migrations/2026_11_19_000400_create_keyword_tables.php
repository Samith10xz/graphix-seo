<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('keyword');
            $table->unsignedInteger('volume')->nullable();
            $table->string('volume_range')->nullable(); // e.g. "1K–10K" when only a range is known
            $table->unsignedTinyInteger('difficulty')->nullable();
            $table->decimal('cpc', 8, 2)->nullable();
            $table->string('intent')->nullable(); // informational | navigational | commercial | transactional
            $table->string('cluster')->nullable();
            $table->string('target_url', 2048)->nullable();
            $table->string('page_category')->nullable();
            $table->string('source')->default('manual'); // manual | import | research | competitor_gap
            $table->boolean('track_rank')->default(true);
            $table->timestamps();
            $table->unique(['project_id', 'keyword']);
        });

        // Result of the placement checks for one keyword on one crawled page.
        Schema::create('keyword_page_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->json('checks'); // {title:true, meta:false, h1:..., density:1.2, ...}
            $table->boolean('is_target')->default(false);
            $table->timestamps();
            $table->unique(['keyword_id', 'page_id']);
        });

        Schema::create('rank_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
            $table->date('checked_on');
            $table->string('device')->default('desktop');
            $table->unsignedSmallInteger('position')->nullable(); // null = not in top 100
            $table->string('ranking_url', 2048)->nullable();
            $table->json('serp_features')->nullable();
            $table->timestamps();
            $table->unique(['keyword_id', 'checked_on', 'device']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rank_checks');
        Schema::dropIfExists('keyword_page_usage');
        Schema::dropIfExists('keywords');
    }
};
