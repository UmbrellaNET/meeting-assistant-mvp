<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 50) ?: 50));
        $query = Activity::query()
            ->with(['causer', 'subject', 'impersonator'])
            ->latest();

        $this->constrainTenant($request, $query);

        if ($request->filled('log_name')) {
            $query->where('log_name', $request->string('log_name'));
        }
        if ($request->filled('event')) {
            $query->where('event', $request->string('event'));
        }
        if ($request->filled('source')) {
            $query->where('source', $request->string('source'));
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from')?->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to')?->endOfDay());
        }

        $search = trim((string) $request->string('q'));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void {
                $builder->where('description', 'like', $like)
                    ->orWhere('event', 'like', $like)
                    ->orWhere('actor_name', 'like', $like)
                    ->orWhere('actor_email', 'like', $like)
                    ->orWhere('subject_label', 'like', $like)
                    ->orWhere('ip_address', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('country', 'like', $like);
            });
        }

        $logs = $query->paginate($perPage);

        return response()->json([
            'data' => ActivityResource::collection($logs->getCollection())->resolve(),
            'current_page' => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
            'per_page' => $logs->perPage(),
            'total' => $logs->total(),
        ]);
    }

    public function show(Request $request, Activity $activity): JsonResponse
    {
        $this->authorizeActivity($request, $activity);
        $activity->load(['causer', 'subject', 'impersonator']);

        return response()->json((new ActivityResource($activity))->resolve());
    }

    private function constrainTenant(Request $request, $query): void
    {
        $user = $request->user();
        if ($user->isSuperAdmin()) {
            if ($request->filled('tenant_id')) {
                $query->where('tenant_id', $request->string('tenant_id'));
            }

            return;
        }

        $query->where('tenant_id', $user->tenant_id);
    }

    private function authorizeActivity(Request $request, Activity $activity): void
    {
        $user = $request->user();
        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless($activity->tenant_id === $user->tenant_id, 404);
    }
}
