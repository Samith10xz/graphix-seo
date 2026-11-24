<?php

namespace App\Providers;

use App\Contracts\CrawlEngine;
use App\Contracts\KeywordDataProvider;
use App\Modules\AI\AiManager;
use App\Modules\Crawler\Engines\PhpCrawlEngine;
use App\Modules\Crawler\Engines\PythonCrawlEngine;
use App\Modules\Data\DataForSeoClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiManager::class);
        $this->app->bind(KeywordDataProvider::class, DataForSeoClient::class);

        // One setting decides where crawls run: 'php' (Hostinger) or 'python' (VPS engine).
        $this->app->bind(CrawlEngine::class, fn ($app) => config('crawler.engine') === 'python'
            ? $app->make(PythonCrawlEngine::class)
            : $app->make(PhpCrawlEngine::class));
    }

    public function boot(): void
    {
        // Catch mass-assignment and missing-attribute mistakes early outside production.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());
    }
}
