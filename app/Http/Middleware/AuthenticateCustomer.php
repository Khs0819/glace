<?php

namespace App\Http\Middleware;

use App\Services\Auth\CustomerAuthService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves `Authorization: <token>` into the customer making the request.
 *
 * Applied two ways:
 *   `customer`          — a token is required; anything else is 401
 *   `customer.optional` — a valid token binds the customer, a missing or bad
 *                         one is simply nobody
 *
 * The optional mode exists because checkout has to work for a guest, while
 * still attaching the order to an account when one is signed in.
 */
class AuthenticateCustomer
{
    public function __construct(private readonly CustomerAuthService $auth) {}

    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        $customer = $this->auth->resolveToken($this->auth->tokenFromRequest($request));

        if ($customer === null) {
            if ($mode === 'optional') {
                return $next($request);
            }

            // The message the storefront's interceptor matches on to clear its
            // stored session and bounce to the login screen.
            return new JsonResponse(['message' => 'Unauthenticated'], 401);
        }

        // Bound on the `customer` guard rather than the default one so nothing
        // can confuse a storefront account with an admin User.
        $request->setUserResolver(fn () => $customer);

        auth()->guard('customer')->setUser($customer);

        return $next($request);
    }
}
