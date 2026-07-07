<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class Runner extends Model
{
    use HasApiTokens, HasUlids;

    protected $fillable = [
        'name', 'hostname', 'os', 'tags', 'status',
        'last_seen_at', 'current_job_count', 'max_concurrent_jobs',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    protected $attributes = [
        'tags' => '[]',
    ];

    public function executions(): HasMany
    {
        return $this->hasMany(ScriptExecutionResult::class);
    }

    public function isOnline(): bool
    {
        return $this->status === 'online';
    }

    public function hasCapacity(): bool
    {
        return $this->current_job_count < $this->max_concurrent_jobs;
    }

    /**
     * True if this runner's tags are a superset of the given required tags.
     */
    public function satisfiesTags(?array $requiredTags): bool
    {
        if (empty($requiredTags)) {
            return true;
        }

        return empty(array_diff($requiredTags, $this->tags ?? []));
    }

    public function markSeen(): void
    {
        $this->forceFill(['last_seen_at' => now(), 'status' => 'online'])->save();
    }
}
