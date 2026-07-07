<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = [
        'execution_timeout_seconds', 'max_concurrent_executions', 'max_history_records',
        'anthropic_api_key', 'anthropic_model', 'anthropic_effort',
    ];

    protected function casts(): array
    {
        return [
            'anthropic_api_key' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
