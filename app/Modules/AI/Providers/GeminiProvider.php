<?php

namespace App\Modules\AI\Providers;

use App\Contracts\AiProvider;
use App\Modules\AI\AiResponse;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Google Gemini generateContent API. */
class GeminiProvider implements AiProvider
{
    public function __construct(private array $config) {}

    public function name(): string
    {
        return 'gemini';
    }

    public function complete(string $system, string $prompt, array $options = []): AiResponse
    {
        if (empty($this->config['api_key'])) {
            throw new RuntimeException('GEMINI_API_KEY is not set');
        }

        $model = $this->config['model'];
        $generation = ['maxOutputTokens' => $options['max_tokens'] ?? config('ai.max_tokens', 4096)];
        if ($options['json'] ?? false) {
            $generation['responseMimeType'] = 'application/json';
        }

        $response = Http::withHeaders(['x-goog-api-key' => $this->config['api_key']])
            ->timeout(config('ai.timeout', 120))
            ->retry(2, 2000, throw: false)
            ->post("{$this->config['base_url']}/models/{$model}:generateContent", [
                'systemInstruction' => ['parts' => [['text' => $system]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig' => $generation,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini API error '.$response->status().': '.$response->json('error.message', $response->body()));
        }

        $text = collect($response->json('candidates.0.content.parts', []))->pluck('text')->implode('');

        return new AiResponse(
            text: $text,
            model: $model,
            inputTokens: (int) $response->json('usageMetadata.promptTokenCount', 0),
            outputTokens: (int) $response->json('usageMetadata.candidatesTokenCount', 0),
        );
    }
}
