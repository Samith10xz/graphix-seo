<?php

use App\Modules\Plan\Services\PlanService;
use App\Modules\Projects\Models\Project;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Add steps that were added to config/seo_plan.php to existing projects (disabled).
Artisan::command('plan:sync', function (PlanService $plan) {
    $total = 0;
    Project::query()->each(function (Project $project) use ($plan, &$total) {
        $total += $plan->sync($project);
    });
    $this->info("Added {$total} step(s).");
})->purpose('Sync SEO plan steps from config into existing projects');

/*
| After each Git deploy (Hostinger runs composer but not artisan): generate the app key
| if missing and run migrations once per new commit. Cheap no-op when nothing changed.
*/
Artisan::command('app:post-deploy {--force}', function () {
    $head = trim((string) @file_get_contents(base_path('.git/HEAD')));
    $commit = str_starts_with($head, 'ref: ')
        ? trim((string) @file_get_contents(base_path('.git/'.substr($head, 5))))
        : $head;
    $marker = storage_path('app/deployed_commit');

    if (! $this->option('force') && $commit !== '' && trim((string) @file_get_contents($marker)) === $commit) {
        return;
    }

    if (empty(config('app.key'))) {
        $this->call('key:generate', ['--force' => true]);
    }
    $this->call('migrate', ['--force' => true]);
    $this->call('optimize:clear');
    $this->call('plan:sync');

    file_put_contents($marker, $commit);
    $this->info("Deployed {$commit}");
})->purpose('Run migrations and housekeeping once per deployed commit');

// No overlap lock here: the lock needs the cache table, which this command's migrations create.
Schedule::command('app:post-deploy')->everyMinute();

// Hostinger has no long-running workers: process the database queue from cron each minute.
// (On the VPS a dedicated queue worker runs instead, so this is skipped.)
if (config('queue.default') === 'database') {
    Schedule::command('queue:work database --stop-when-empty --max-time=55 --tries=3 --timeout=240')
        ->everyMinute()
        ->withoutOverlapping(5)
        ->runInBackground();
}

Schedule::command('queue:prune-failed --hours=168')->daily();
