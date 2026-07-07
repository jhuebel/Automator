<?php

namespace App\Models;

use App\Enums\ScriptLanguage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScriptExecutionResult extends Model
{
    use HasUlids;

    protected $fillable = [
        'script_id', 'runner_id', 'script_name', 'language', 'username',
        'started_at', 'completed_at', 'exit_code', 'output', 'pid', 'cancel_requested_at',
    ];

    protected function casts(): array
    {
        return [
            'language' => ScriptLanguage::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancel_requested_at' => 'datetime',
            'output' => 'array',
        ];
    }

    protected $attributes = [
        'output' => '[]',
    ];

    public function script(): BelongsTo
    {
        return $this->belongsTo(ScriptDefinition::class, 'script_id');
    }

    public function runner(): BelongsTo
    {
        return $this->belongsTo(Runner::class);
    }

    protected function isRunning(): Attribute
    {
        return Attribute::get(fn () => $this->completed_at === null);
    }

    protected function isSuccess(): Attribute
    {
        return Attribute::get(fn () => $this->exit_code === 0);
    }

    /**
     * Duration in seconds, or null while still running.
     */
    protected function durationSeconds(): Attribute
    {
        return Attribute::get(fn () => $this->completed_at
            ? $this->started_at->diffInSeconds($this->completed_at)
            : null);
    }
}
