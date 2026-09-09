<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Support\ActivityContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();
        abort_unless($plainToken, 401, 'Missing API token.');

        $token = ApiToken::query()
            ->with(['user.tenant', 'impersonator'])
            ->where('token_hash', hash('sha256', $plainToken))
            ->whereNull('revoked_at')
            ->first();

        abort_unless($token?->user, 401, 'Invalid API token.');
        abort_if($token->isExpired(), 401, 'Invalid API token.');
        $token->forceFill(['last_used_at' => now()])->save();

        $user = $token->user;
        $request->attributes->set('apiToken', $token);
        $request->setUserResolver(fn () => $user);
        Auth::setUser($user);
        app(ActivityContext::class)->authenticate($user, $token);
        setPermissionsTeamId($user->tenant_id);

        return $next($request);
    }
}
