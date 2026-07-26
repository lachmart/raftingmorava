<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Verifies the shared secret token in the X-Inquiry-Token header against INQUIRY_WEBHOOK_SECRET.
class VerifyInquiryToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('company.inquiry_secret');
        $provided = (string) $request->header('X-Inquiry-Token', '');

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Invalid token.'], 401);
        }

        return $next($request);
    }
}
