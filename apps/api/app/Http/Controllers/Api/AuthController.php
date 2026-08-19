<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate(['name'=>'required|string|max:120','organisation'=>'required|string|max:160','email'=>'required|email|max:190|unique:users,email','password'=>'required|string|min:10']);
        [$user, $plainToken] = DB::transaction(function () use ($data): array {
            $tenant = Tenant::create(['name'=>$data['organisation'],'slug'=>Str::slug($data['organisation']).'-'.Str::lower(Str::random(5))]);
            $user = User::create(['tenant_id'=>$tenant->id,'name'=>$data['name'],'email'=>$data['email'],'password'=>$data['password']]);
            return [$user, $this->issueToken($user, 'initial')];
        });
        return response()->json(['token'=>$plainToken,'user'=>$user->load('tenant')], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email'=>'required|email','password'=>'required|string']);
        $user = User::where('email', $data['email'])->first();
        abort_unless($user && Hash::check($data['password'], $user->password), 422, 'Invalid credentials.');
        return response()->json(['token'=>$this->issueToken($user, 'web'),'user'=>$user->load('tenant')]);
    }

    public function me(Request $request): JsonResponse { return response()->json($request->user()->load('tenant')); }

    public function logout(Request $request): JsonResponse
    {
        $hash = hash('sha256', (string) $request->bearerToken());
        ApiToken::where('token_hash', $hash)->update(['revoked_at'=>now()]);
        return response()->json(['message'=>'Logged out.']);
    }

    private function issueToken(User $user, string $name): string
    {
        $plain = Str::random(64);
        ApiToken::create(['user_id'=>$user->id,'name'=>$name,'token_hash'=>hash('sha256', $plain)]);
        return $plain;
    }
}
