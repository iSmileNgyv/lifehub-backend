<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Telegram\TelegramRouter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Telegram webhook (prod). URL-də secret + header secret yoxlanır. */
class TelegramWebhookController extends Controller
{
    public function handle(Request $request, string $secret, TelegramRouter $router): Response
    {
        $expected = (string) config('services.telegram.webhook_secret');
        abort_unless(hash_equals($expected, $secret), 404);

        $header = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
        if ($header !== '' && ! hash_equals($expected, $header)) {
            abort(403);
        }

        $router->handle($request->all());

        return response()->noContent();
    }
}
