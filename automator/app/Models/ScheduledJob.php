<?php

namespace App\Models;

use Cron\CronExpression;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledJob extends Model
{
    use HasUlids;

    protected $fillable = [
        'name', 'script_id', 'cron_expression', 'is_enabled',
        'last_run_at', 'next_run_at', 'last_exit_code', 'current_execution_id',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function script(): BelongsTo
    {
        return $this->belongsTo(ScriptDefinition::class, 'script_id');
    }

    protected function lastRunSucceeded(): Attribute
    {
        return Attribute::get(fn () => $this->last_exit_code === 0);
    }

    public static function nextOccurrence(string $cronExpression): ?\DateTime
    {
        try {
            return CronExpression::factory($cronExpression)
                ->getNextRunDate(now(), timeZone: 'UTC');
        } catch (\Throwable) {
            return null;
        }
    }

    public function refreshNextRunAt(): void
    {
        $this->next_run_at = static::nextOccurrence($this->cron_expression);
    }
}
