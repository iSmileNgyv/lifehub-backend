<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sorğunun dilini təyin edir:
 *  1) Accept-Language başlığı (frontend göndərir — dil dəyişən kimi dərhal təsir edir)
 *  2) daxil olmuş istifadəçinin saxlanmış dili
 *  3) default 'az'
 */
class SetLocale
{
    private const SUPPORTED = ['az', 'en', 'ru'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = null;

        $header = $request->header('Accept-Language');
        if ($header) {
            $first = strtolower(substr(trim(explode(',', $header)[0]), 0, 2));
            if (in_array($first, self::SUPPORTED, true)) {
                $locale = $first;
            }
        }

        if (! $locale && ($user = $request->user()) && $user->language) {
            $locale = $user->language->value;
        }

        app()->setLocale($locale ?? 'az');

        return $next($request);
    }
}
