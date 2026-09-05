<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the customer when a valid token is present, and lets a guest through
 * when it is not.
 *
 * A separate class rather than `customer:optional` so the mode is visible in
 * the route list — "which routes work signed out" is a security question, and
 * it should be answerable by reading routes/api.php.
 */
class AuthenticateCustomerOptional extends AuthenticateCustomer
{
    public function handle(Request $request, Closure $next, string $mode = 'optional'): Response
    {
        return parent::handle($request, $next, 'optional');
    }
}
