<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi API Token untuk endpoint penerimaan data HQ (PRD 2.2).
 * Pabrik mengirim header: X-HQ-API-TOKEN: <shared-token>
 */
class VerifyHQToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('hq.api_token');
        $provided = (string) $request->header('X-HQ-API-TOKEN', '');

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'error' => 'Unauthorized: token API HQ tidak valid.',
            ], 401);
        }

        return $next($request);
    }
}
