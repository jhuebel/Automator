<?php

namespace App\Models;

use App\Enums\ScriptLanguage;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScriptDefinition extends Model
{
    use HasUlids;

    protected $fillable = [
        'name', 'description', 'language', 'content', 'tags', 'variables',
    ];

    protected function casts(): array
    {
        return [
            'language' => ScriptLanguage::class,
            'tags' => 'array',
            'variables' => 'array',
        ];
    }

    protected $attributes = [
        'tags' => '[]',
        'variables' => '[]',
    ];

    public function executions(): HasMany
    {
        return $this->hasMany(ScriptExecutionResult::class, 'script_id');
    }
}
