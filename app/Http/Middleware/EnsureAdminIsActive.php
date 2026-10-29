<?php

namespace App\Http\Middleware;

use App\Exceptions\RbacException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defence in depth. Deactivation already revokes tokens, but a token that
 * survives for any reason is refused here as well.
 */
final class EnsureAdminIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        if (! $user->is_active) {
            $user->tokens()->delete();

            throw RbacException::accountInactive();
        }

        return $next($request);
    }
}
