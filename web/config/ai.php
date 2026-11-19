<?php

/*
|--------------------------------------------------------------------------
| AI layer
|--------------------------------------------------------------------------
| Each task type is routed to a provider. Change routing in .env without
| touching code. Add a provider: implement App\Contracts\AiProvider and
| register it under 'providers'.
*/

return [
    'tasks' => [
        // audit summaries, competitor insights, roadmap: quality first
        'analysis' => env('AI_TASK_ANALYSIS', 'claude'),
        // meta titles, content briefs, outreach emails
        'writing' => env('AI_TASK_WRITING', 'claude'),
        // intent labels, page summaries at volume: cost first
        'bulk' => env('AI_TASK_BULK', 'gemini'),
    ],

    'providers' => [
        'claude' => [
            'class' => App\Modules\AI\Providers\ClaudeProvider::class,
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('CLAUDE_MODEL', 'claude-sonnet-5-5'),
            'fast_model' => env('CLAUDE_FAST_MODEL', 'claude-haiku-5-5'),
            'base_url' => 'https://api.anthropic.com/v1',
            'version' => '2023-06-01',
        ],
        'gemini' => [
            'class' => App\Modules\AI\Providers\GeminiProvider::class,
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        ],
    ],

    'timeout' => 120,
    'max_tokens' => 4096,
];
