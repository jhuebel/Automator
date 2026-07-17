<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class RunnerRelease extends Model
{
    use HasUlids;

    protected $fillable = [
        'version', 'os', 'arch', 'checksum_sha256', 'storage_path', 'size_bytes',
        'is_released', 'released_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_released' => 'boolean',
            'released_at' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }

    /**
     * The release a runner of this os/arch should update to, if any —
     * released builds only, most-recently-released first. Publish/release
     * order (not semver comparison) is the operationally meaningful signal:
     * a rollback is just releasing an older version again.
     */
    public static function latestFor(string $os, string $arch): ?self
    {
        return static::query()
            ->where('os', $os)
            ->where('arch', $arch)
            ->where('is_released', true)
            ->orderByDesc('released_at')
            ->first();
    }
}
