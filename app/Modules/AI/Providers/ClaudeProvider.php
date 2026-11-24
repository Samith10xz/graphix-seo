<?php

namespace App\Modules\AI\Providers;

use App\Contracts\AiProvider;
use App\Modules\AI\AiResponse;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Anthropic Messages API. */
class ClaudeProvider implements AiProvider
{
    public function __construct(private array $config) {}

    public function name(): string
    {
        return 'claude';
    }

    public function complete(string $system, string $prompt, array $options = []): AiResponse
    {
        if (empty($this->config['api_key'])) {
            throw new RuntimeException('ANTHROPIC_API_KEY is not set');
        }

        $model = ($options['fast'] ?? false) ? $this->config['fast_model'] : $this->config['model'];
        if ($options['json'] ?? false) {
            $system .= "\nRespond with valid JSON only, no other text.";
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->config['api_key'],
            'anthropic-version' => $this->config['version'],
        ])
            ->timeout(config('ai.timeout', 120))
            ->retry(2, 2000, throw: false)
            ->post($this->config['base_url'].'/messages', [
                'model' => $model,
                'max_tokens' => $options['max_tokens'] ?? config('ai.max_tokens', 4096),
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Claude API error '.$response->status().': '.$response->json('error.message', $response->body()));
        }

        $text = collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        return new AiResponse(
            text: $text,
            model: $response->json('model', $model),
            inputTokens: (int) $response->json('usage.input_tokens', 0),
            outputTokens: (int) $response->json('usage.output_tokens', 0),
        );
    }
}
