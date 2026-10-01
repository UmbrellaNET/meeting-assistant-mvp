<?php  

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class AdminRoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $roles = Role::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with('permissions')
            ->orderBy('name')
            ->get();

        return response()->json($roles->map(fn (Role $role) => [
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->values()->all(),
        ])->values());
    }
}
