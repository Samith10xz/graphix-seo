<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Only the Python engine (holding ENGINE_API_KEY) may call internal routes. */
class VerifyEngineKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.engine.key');

        if ($expected === '' || ! hash_equals($expected, (string) $request->header('X-Engine-Key'))) {
            abort(401, 'Invalid engine key');
        }

        return $next($request);
    }
}
