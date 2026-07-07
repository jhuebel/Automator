<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasUlids;

    const UPDATED_AT = null;

    protected $fillable = ['username', 'action', 'resource', 'details'];

    public static function record(string $action, ?string $resource = null, ?string $details = null, ?string $username = null): void
    {
        static::create([
            'username' => $username ?? auth()->user()?->username,
            'action' => $action,
            'resource' => $resource,
            'details' => $details,
        ]);
    }
}
