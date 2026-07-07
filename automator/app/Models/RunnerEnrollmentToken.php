<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RunnerEnrollmentToken extends Model
{
    use HasUlids;

    protected $fillable = ['token_hash', 'expires_at', 'used_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * Issue a new enrollment token. Returns the plaintext value — shown once,
     * only the hash is persisted.
     */
    public static function issue(?int $creatorId, int $ttlMinutes = 60): string
    {
        $plaintext = Str::random(40);

        static::create([
            'token_hash' => hash('sha256', $plaintext),
            'expires_at' => now()->addMinutes($ttlMinutes),
            'created_by' => $creatorId,
        ]);

        return $plaintext;
    }

    /**
     * Validate and consume a plaintext enrollment token. Returns the token
     * record if valid (and marks it used), or null if invalid/expired/used.
     */
    public static function redeem(string $plaintext): ?self
    {
        $token = static::query()
            ->where('token_hash', hash('sha256', $plaintext))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        $token?->update(['used_at' => now()]);

        return $token;
    }
}
