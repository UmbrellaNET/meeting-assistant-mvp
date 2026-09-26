<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\ApiToken;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RecordsActivity;
use App\Services\TenantRoleProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Facades\LogBatch;

class AuthController extends Controller
{
    public function register(Request $request, TenantRoleProvisioner $provisioner): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'organisation' => 'required|string|max:160',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => 'required|string|min:10',
        ]);

        [$user, $plainToken] = DB::transaction(function () use ($data, $provisioner): array {
            LogBatch::startBatch();
            try {
                $tenant = Tenant::create([
                    'name' => $data['organisation'],
                    'slug' => Str::slug($data['organisation']).'-'.Str::lower(Str::random(5)),
                ]);
                $parts = User::splitName($data['name']);
                $user = User::create([
                    'tenant_id' => $tenant->id,
                    'name' => User::composeName($parts['first_name'], $parts['surname']) ?: $data['name'],
                    'first_name' => $parts['first_name'],
                    'surname' => $parts['surname'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                ]);
                $provisioner->provision($tenant);
                $user->assignRole('super-admin');

                RecordsActivity::make()
                    ->event(ActivityEvent::AuthRegistered)
                    ->causedBy($user)
                    ->performedOn($user)
                    ->onTenant($tenant)
                    ->log();

                return [$user, $this->issueToken($user, 'initial')];
            } finally {
                LogBatch::endBatch();
            }
        });

        return response()->json([
            'token' => $plainToken,
            'user' => (new UserResource($user))->resolve(),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RecordsActivity::make()
                ->event(ActivityEvent::AuthLoginFailed)
                ->causedBy($user)
                ->onTenant($user?->tenant_id)
                ->withProperties(['email' => $data['email']])
                ->log();
            abort(422, 'Invalid credentials.');
        }
        setPermissionsTeamId($user->tenant_id);

        RecordsActivity::make()
            ->event(ActivityEvent::AuthLogin)
            ->causedBy($user)
            ->performedOn($user)
            ->log();

        return response()->json([
            'token' => $this->issueToken($user, 'web'),
            'user' => (new UserResource($user))->resolve(),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json((new UserResource($request->user()))->resolve());
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('profile.update'), 403, 'Forbidden.');

        $data = $request->validate([
            'name' => 'sometimes|string|max:120',
            'first_name' => 'sometimes|string|max:80',
            'surname' => 'sometimes|string|max:80',
            'email' => ['sometimes', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'contact_number' => 'sometimes|nullable|string|max:40',
            'job_title' => 'sometimes|nullable|string|max:120',
            'department' => 'sometimes|nullable|string|max:120',
            'timezone' => 'sometimes|nullable|string|max:80',
            'bio' => 'sometimes|nullable|string|max:500',
            'password' => 'sometimes|string|min:8',
            'current_password' => 'required_with:password|string',
        ]);

        if (isset($data['password'])) {
            $token = $request->attributes->get('apiToken');
            abort_if(
                $token instanceof ApiToken && $token->isImpersonation(),
                422,
                'Cannot change password while impersonating.'
            );
            abort_unless(
                Hash::check($data['current_password'] ?? '', $user->password),
                422,
                'Current password is incorrect.'
            );
            $user->password = $data['password'];
            RecordsActivity::make()
                ->event(ActivityEvent::AuthPasswordChanged)
                ->causedBy($user)
                ->performedOn($user)
                ->log();
        }

        if (isset($data['first_name']) || isset($data['surname'])) {
            $user->applyNameParts(
                $data['first_name'] ?? $user->first_name ?? '',
                $data['surname'] ?? $user->surname ?? '',
            );
        } elseif (isset($data['name'])) {
            $user->applyFullName($data['name']);
        }

        $user->fill(collect($data)->only([
            'email',
            'contact_number',
            'job_title',
            'department',
            'timezone',
            'bio',
        ])->all());
        $user->save();

        return response()->json((new UserResource($user->fresh('tenant')))->resolve());
    }

    public function uploadPhoto(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('profile.update'), 403, 'Forbidden.');

        $data = $request->validate([
            'file' => [
                'required',
                'file',
                'max:2048',
                'mimetypes:image/png,image/jpeg,image/webp',
            ],
        ]);

        $file = $data['file'];
        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');
        if (! in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            $extension = 'png';
        }
        $key = "tenants/{$user->tenant_id}/users/{$user->id}/avatar.{$extension}";

        if ($user->avatar_storage_key && $user->avatar_storage_key !== $key) {
            Storage::disk(config('filesystems.default'))->delete($user->avatar_storage_key);
        }

        $file->storeAs(dirname($key), basename($key), config('filesystems.default'));
        $user->update(['avatar_storage_key' => $key]);

        return response()->json((new UserResource($user->fresh('tenant')))->resolve());
    }

    public function destroyPhoto(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('profile.update'), 403, 'Forbidden.');

        if ($user->avatar_storage_key) {
            Storage::disk(config('filesystems.default'))->delete($user->avatar_storage_key);
            $user->update(['avatar_storage_key' => null]);
        }

        return response()->json((new UserResource($user->fresh('tenant')))->resolve());
    }

    public function logout(Request $request): JsonResponse
    {
        RecordsActivity::make()
            ->event(ActivityEvent::AuthLogout)
            ->causedBy($request->user())
            ->performedOn($request->user())
            ->log();

        $hash = hash('sha256', (string) $request->bearerToken());
        ApiToken::where('token_hash', $hash)->update(['revoked_at' => now()]);

        return response()->json(['message' => 'Logged out.']);
    }

    public function stopImpersonation(Request $request): JsonResponse
    {
        $token = $request->attributes->get('apiToken');
        abort_unless($token instanceof ApiToken && $token->isImpersonation(), 422, 'Not impersonating.');

        $token->forceFill(['revoked_at' => now()])->save();

        RecordsActivity::make()
            ->event(ActivityEvent::ImpersonationStopped)
            ->causedBy($request->user())
            ->performedOn($request->user())
            ->impersonatedBy($token->impersonator)
            ->log();

        return response()->json(['ok' => true]);
    }

    private function issueToken(User $user, string $name): string
    {
        return ApiToken::issue($user, $name);
    }
}
