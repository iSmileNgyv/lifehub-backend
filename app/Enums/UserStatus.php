<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Banned = 'banned';

    /**
     * Yalnız bu statusda login etməyə icazə var.
     */
    public function canLogin(): bool
    {
        return $this === self::Active;
    }
}
