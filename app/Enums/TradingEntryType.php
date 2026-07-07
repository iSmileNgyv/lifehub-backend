<?php

namespace App\Enums;

enum TradingEntryType: string
{
    case Buy = 'buy';   // USD alışı — FIFO təbəqə (giriş)
    case Sell = 'sell'; // USD satışı — FIFO çıxış
}
