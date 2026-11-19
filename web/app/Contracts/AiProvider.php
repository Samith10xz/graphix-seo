<?php

namespace App\Contracts;

use App\Modules\AI\AiResponse;

interface AiProvider
{
    /**
     * Send one prompt and return the text plus token usage.
     *
     * @param  string  $system  instructions for the model
     * @param  string  $prompt  the user content (data to analyse, task)
     * @param  array{fast?:bool, max_tokens?:int, json?:bool}  $options
     */
    public function complete(string $system, string $prompt, array $options = []): AiResponse;

    /** Short provider name stored in ai_requests (claude, gemini, ...). */
    public function name(): string;
}
