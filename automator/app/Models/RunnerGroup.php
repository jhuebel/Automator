<?php

namespace App\Models;

use App\Enums\ScriptLanguage;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RunnerGroup extends Model
{
    use HasUlids;

    protected $fillable = ['name', 'description'];

    public function runners(): BelongsToMany
    {
        return $this->belongsToMany(Runner::class, 'runner_group_runner');
    }

    /**
     * True if any member runner satisfies the required tags — a group is
     * eligible if at least one of its runners is, since a script execution
     * always ultimately runs on a single concrete runner.
     */
    public function satisfiesTags(?array $requiredTags): bool
    {
        return $this->runners->contains(fn (Runner $runner) => $runner->satisfiesTags($requiredTags));
    }

    /**
     * True if any member runner reports this language as available — the
     * aggregate/union of capabilities across the group, not a requirement
     * that every member support it.
     */
    public function supportsLanguage(ScriptLanguage $language): bool
    {
        return $this->runners->contains(fn (Runner $runner) => $runner->supportsLanguage($language));
    }
}
