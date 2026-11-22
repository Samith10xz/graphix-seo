<?php

namespace App\Modules\AI;

use App\Contracts\AiProvider;
use App\Modules\AI\Models\AiRequest;
use App\Modules\Projects\Models\Project;
use InvalidArgumentException;
use Throwable;

/**
 * One entry point for all AI calls. Picks the provider configured for the task,
 * logs usage per project, and keeps providers swappable.
 *
 *   $ai->run('writing', 'meta_titles', $project, system: '...', prompt: '...')
 */
class AiManager
{
    /** @var array<string, AiProvider> */
    private array $resolved = [];

    public function run(string $task, string $feature, ?Project $project, string $system, string $prompt, array $options = []): AiResponse
    {
        $provider = $this->providerFor($task);
        $started = hrtime(true);

        try {
            $response = $provider->complete($system, $prompt, $options + ['fast' => $task === 'bulk']);
            $this->log($task, $feature, $project, $provider->name(), $response->model, $started, $response);

            return $response;
        } catch (Throwable $e) {
            $this->log($task, $feature, $project, $provider->name(), 'unknown', $started, null, $e->getMessage());
            throw $e;
        }
    }

    public function providerFor(string $task): AiProvider
    {
        $name = config("ai.tasks.{$task}");
        if (! $name) {
            throw new InvalidArgumentException("No AI provider configured for task '{$task}'");
        }

        return $this->provider($name);
    }

    public function provider(string $name): AiProvider
    {
        if (! isset($this->resolved[$name])) {
            $config = config("ai.providers.{$name}");
            if (! $config) {
                throw new InvalidArgumentException("Unknown AI provider '{$name}'");
            }
            $this->resolved[$name] = new $config['class']($config);
        }

        return $this->resolved[$name];
    }

    private function log(string $task, string $feature, ?Project $project, string $provider, string $model, int $started, ?AiResponse $r, ?string $error = null): void
    {
        AiRequest::create([
            'project_id' => $project?->id,
            'task' => $task,
            'feature' => $feature,
            'provider' => $provider,
            'model' => $model,
            'input_tokens' => $r?->inputTokens ?? 0,
            'output_tokens' => $r?->outputTokens ?? 0,
            'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            'success' => $error === null,
            'error' => $error,
        ]);
    }
}
