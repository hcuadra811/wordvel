<?php

declare(strict_types=1);

namespace Wordvel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Wordvel\Bridge\BridgeKey;

/**
 * Accepts a request only when it carries the WordPress bridge key. With no key
 * resolvable (no WORDVEL_BRIDGE_KEY and no APP_KEY) every request is refused.
 */
final class VerifyBridgeKey
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! BridgeKey::matches($request->header(BridgeKey::HEADER))) {
            return response()->json([
                'status' => false,
                'error' => [
                    'message' => 'This endpoint only accepts requests from the WordVel WordPress bridge.',
                ],
            ], 401);
        }

        return $next($request);
    }
}
