<?php

namespace App\Enums;

enum ItemStatus: string
{
    case Active = 'ACTIVE';
    case Blocked = 'BLOCKED';
}
