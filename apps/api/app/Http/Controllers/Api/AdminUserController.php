<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\RecordsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->get();

        return response()->json($users->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->values()->all(),
        ])->values());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'nullable|in:user,super-admin',
        ]);

        $parts = User::splitName($data['name']);
        $user = User::create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => User::composeName($parts['first_name'], $parts['surname']) ?: $data['name'],
            'first_name' => $parts['first_name'],
            'surname' => $parts['surname'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        $user->assignRole($data['role'] ?? 'user');

        RecordsActivity::make()
            ->event('user.role_assigned')
            ->inLog('users')
            ->causedBy($request->user())
            ->performedOn($user)
            ->withProperties(['role' => $data['role'] ?? 'user'])
            ->log();

        return response()->json((new UserResource($user))->resolve(), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($user->tenant_id === $request->user()->tenant_id, 404);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'role' => 'nullable|in:user,super-admin',
        ]);

        $user->fill([
            'email' => $data['email'],
        ]);
        $user->applyFullName($data['name']);
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        if (isset($data['role'])) {
            $previous = $user->getRoleNames()->first();
            $user->syncRoles([$data['role']]);
            if ($previous !== $data['role']) {
                RecordsActivity::make()
                    ->event('user.role_changed')
                    ->inLog('users')
                    ->causedBy($request->user())
                    ->performedOn($user)
                    ->withProperties(['old' => $previous, 'new' => $data['role']])
                    ->log();
            }
        }

        return response()->json((new UserResource($user->fresh()))->resolve());
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_unless($user->tenant_id === $request->user()->tenant_id, 404);
        abort_if($user->id === $request->user()->id, 422, 'You cannot delete your own account.');

        $user->delete();

        return response()->json(['ok' => true]);
    }

    public function impersonate(Request $request, User $user): JsonResponse
    {
        $admin = $request->user();
        abort_unless($user->tenant_id === $admin->tenant_id, 404);

        $currentToken = $request->attributes->get('apiToken');
        abort_if($user->id === $admin->id, 422, 'You cannot impersonate yourself.');
        abort_if(
            $currentToken instanceof ApiToken && $currentToken->isImpersonation(),
            422,
            'Already impersonating.'
        );
        abort_unless($admin->isSuperAdmin(), 403, 'Forbidden.');

        ApiToken::query()
            ->where('impersonator_id', $admin->id)
            ->whereNull('revoked_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->update(['revoked_at' => now()]);

        $expiresAt = now()->addHours(2);
        $plain = ApiToken::issue($user, 'impersonation', $admin->id, $expiresAt);

        RecordsActivity::make()
            ->event(ActivityEvent::ImpersonationStarted)
            ->causedBy($user)
            ->performedOn($user)
            ->impersonatedBy($admin)
            ->withProperties([
                'impersonator' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                ],
            ])
            ->log();

        $impersonator = [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
        ];
        $userPayload = (new UserResource($user->fresh('tenant')))->resolve($request);
        $userPayload['impersonation'] = [
            'active' => true,
            'impersonator' => $impersonator,
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        return response()->json([
            'token' => $plain,
            'user' => $userPayload,
            'impersonator' => $impersonator,
        ]);
    }
}
