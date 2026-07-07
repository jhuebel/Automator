<?php

namespace App\Enums;

enum ScriptVariableType: string
{
    case Text = 'Text';
    case Number = 'Number';
    case Boolean = 'Boolean';
    case ListType = 'List';

    public function label(): string
    {
        return match ($this) {
            self::ListType => 'List',
            default => $this->value,
        };
    }
}
