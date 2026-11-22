<?php

namespace App\Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;

class AiRequest extends Model
{
    protected $fillable = ['project_id', 'task', 'feature', 'provider', 'model', 'input_tokens', 'output_tokens', 'duration_ms', 'success', 'error'];

    protected function casts(): array
    {
        return ['success' => 'boolean'];
    }
}
