<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards POST /mcp:
 *  - a bearer token when MCP_TOKEN is set (compared in constant time), and
 *  - an Origin check, which the MCP spec asks for to stop DNS-rebinding
 *    attacks from a web page in the user's browser.
 */
class AuthenticateMcp
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');
        if ($origin !== null && ! $this->originAllowed($origin)) {
            return response()->json(['error' => 'Origin not allowed'], 403);
        }

        $token = (string) config('mcp.token');
        if ($token !== '' && ! hash_equals($token, (string) $request->bearerToken())) {
            return response()->json(['error' => 'Unauthorized'], 401)
                ->header('WWW-Authenticate', 'Bearer');
        }

        return $next($request);
    }

    private function originAllowed(string $origin): bool
    {
        $host = parse_url($origin, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return in_array($host, array_filter(['localhost', '127.0.0.1', $appHost]), true);
    }
}
