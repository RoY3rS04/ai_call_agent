<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twilio\Security\RequestValidator;

class ValidateTwilioRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        $signature = $request->header('X-Twilio-Signature');

        $validator = new RequestValidator(config('services.twilio.auth_token'));

        $isValid = $validator->validate(
            $signature,
            $request->fullUrl(),
            $request->toArray()
        );

        if (!$isValid) {
            return \response('Invalid signature', Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
