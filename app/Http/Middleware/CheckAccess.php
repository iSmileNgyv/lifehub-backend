<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * İstifadə: ->middleware('access:PRODUCT_DELETE')
 * super_admin keçir; əks halda user-in hər hansı rolu access=1 verirsə keçir (additive); yoxsa 403.
 */
class CheckAccess
{
    public function handle(Request $request, Closure $next, string $operation): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if (! $user->hasOperation($operation)) {
            abort(403, __('messages.no_permission'));
        }

        return $next($request);
    }
}
