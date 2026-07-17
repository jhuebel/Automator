<?php

namespace App\Models;

use App\Enums\ScriptLanguage;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class Runner extends Model
{
    use HasApiTokens, HasUlids;

    protected $fillable = [
        'name', 'hostname', 'os', 'version', 'arch', 'disk_free_bytes', 'disk_total_bytes',
        'tags', 'runtimes', 'status', 'last_seen_at', 'current_job_count', 'max_concurrent_jobs',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'runtimes' => 'array',
            'last_seen_at' => 'datetime',
            'disk_free_bytes' => 'integer',
            'disk_total_bytes' => 'integer',
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

    /**
     * True if this runner's last heartbeat reported the interpreter/tool for
     * this language as available. A runner that hasn't heartbeated yet (no
     * runtimes reported) is treated as unsupported for everything — safer
     * than assuming compatibility for an unknown host.
     */
    public function supportsLanguage(ScriptLanguage $language): bool
    {
        $runtime = collect($this->runtimes ?? [])->firstWhere('name', $language->runtimeName());

        return (bool) ($runtime['available'] ?? false);
    }
}
