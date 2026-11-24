<?php

namespace App\Modules\AI;

final class AiResponse
{
    public function __construct(
        public readonly string $text,
        public readonly string $model,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
    ) {}

    /** Decode a JSON answer, tolerating ```json fences. */
    public function json(): ?array
    {
        $raw = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($this->text)));
        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }
}
