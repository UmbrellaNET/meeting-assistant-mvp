<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json($users);
    }
}
