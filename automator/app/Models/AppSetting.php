<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = [
        'execution_timeout_seconds', 'max_history_records',
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
        $settings = static::query()->firstOrCreate([]);

        // firstOrCreate()'s insert only sets the attributes it was given (none
        // here), so a freshly-created row's DB-level column defaults (e.g.
        // execution_timeout_seconds) aren't reflected on the in-memory model
        // until it's reloaded.
        if ($settings->wasRecentlyCreated) {
            $settings->refresh();
        }

        return $settings;
    }
}
