<?php

namespace App\Http\Resources;

use App\Models\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $user = $this->resource;
        if ($user->tenant_id) {
            setPermissionsTeamId($user->tenant_id);
        }
        $user->loadMissing('tenant');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'surname' => $user->surname,
            'email' => $user->email,
            'contact_number' => $user->contact_number,
            'job_title' => $user->job_title,
            'department' => $user->department,
            'timezone' => $user->timezone,
            'bio' => $user->bio,
            'avatar_url' => $user->avatarUrl(),
            'has_avatar' => (bool) $user->avatar_storage_key,
            'tenant' => $user->tenant,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'is_super_admin' => $user->isSuperAdmin(),
            'impersonation' => $this->impersonationState($request),
        ];
    }

    /**
     * @return array{active: bool, impersonator: array{id: string, name: string, email: string}|null, expires_at: string|null}|null
     */
    private function impersonationState(Request $request): ?array
    {
        $token = $request->attributes->get('apiToken');
        if (
            ! $token instanceof ApiToken
            || ! $token->isImpersonation()
            || $this->resource->id !== $request->user()?->id
        ) {
            return null;
        }

        return [
            'active' => true,
            'impersonator' => $token->impersonator ? [
                'id' => $token->impersonator->id,
                'name' => $token->impersonator->name,
                'email' => $token->impersonator->email,
            ] : null,
            'expires_at' => $token->expires_at?->toIso8601String(),
        ];
    }
}
