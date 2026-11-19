<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->string('domain')->index();
            $table->string('country', 2)->default('LK');
            $table->string('language', 5)->default('en');
            $table->string('niche')->nullable();
            $table->json('settings')->nullable(); // crawl limits, JS rendering, schedule
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // The SEO plan of one project: copied from config/seo_plan.php, then editable.
        Schema::create('project_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->unsignedSmallInteger('position');
            $table->boolean('enabled')->default(true);
            $table->boolean('custom')->default(false);   // added for this client only
            $table->string('status')->default('locked'); // locked | ready | generating | review | approved
            $table->json('data')->nullable();            // the step's working content
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'key']);
            $table->index(['project_id', 'position']);
        });

        Schema::create('competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('source')->default('auto'); // auto | manual
            $table->boolean('included')->default(true);
            $table->unsignedTinyInteger('domain_authority')->nullable();
            $table->unsignedInteger('referring_domains')->nullable();
            $table->unsignedInteger('organic_keywords')->nullable();
            $table->unsignedInteger('estimated_traffic')->nullable();
            $table->unsignedInteger('shared_keywords')->nullable();
            $table->json('metrics')->nullable(); // anything else a provider returns
            $table->timestamp('metrics_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitors');
        Schema::dropIfExists('project_steps');
        Schema::dropIfExists('projects');
    }
};
