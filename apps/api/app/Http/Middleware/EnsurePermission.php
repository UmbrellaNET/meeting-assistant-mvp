<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated.');

        $needed = collect($permissions)
            ->flatMap(fn (string $permission) => explode('|', $permission))
            ->filter()
            ->values()
            ->all();

        $allowed = collect($needed)->contains(fn (string $permission) => $user->can($permission));
        abort_unless($allowed, 403, 'Forbidden.');

        return $next($request);
    }
}
