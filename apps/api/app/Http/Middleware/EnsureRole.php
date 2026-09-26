<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated.');

        $needed = collect($roles)
            ->flatMap(fn (string $role) => explode('|', $role))
            ->filter()
            ->values()
            ->all();

        abort_unless($user->hasAnyRole($needed), 403, 'Forbidden.');

        return $next($request);
    }
}
