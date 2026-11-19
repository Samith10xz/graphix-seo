<?php

namespace App\Providers;

use App\Contracts\KeywordDataProvider;
use App\Modules\AI\AiManager;
use App\Modules\Data\DataForSeoClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiManager::class);
        $this->app->bind(KeywordDataProvider::class, DataForSeoClient::class);
    }

    public function boot(): void
    {
        // Catch mass-assignment and missing-attribute mistakes early outside production.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());
    }
}
