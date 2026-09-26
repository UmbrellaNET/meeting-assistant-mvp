<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandingController extends Controller
{
    private const ASSET_TYPES = ['logo', 'icon', 'favicon'];

    private const DEFAULT_COLORS = [
        'primary' => '#D040C0',
        'primaryHover' => '#A03090',
        'primarySoft' => '#F6E8F4',
        'action' => '#D040C0',
        'actionHover' => '#A03090',
        'derivedFromLogo' => false,
    ];

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($request->user()->tenant));
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('branding.manage'), 403, 'Forbidden.');

        $hex = ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        $data = $request->validate([
            'primary' => $hex,
            'primaryHover' => $hex,
            'primarySoft' => $hex,
            'action' => $hex,
            'actionHover' => $hex,
            'derivedFromLogo' => ['sometimes', 'boolean'],
        ]);

        $tenant = $request->user()->tenant;
        $tenant->update([
            'branding' => array_merge(self::DEFAULT_COLORS, $data, [
                'derivedFromLogo' => (bool) ($data['derivedFromLogo'] ?? false),
            ]),
        ]);

        return response()->json($this->payload($tenant->fresh()));
    }

    public function upload(Request $request, string $type): JsonResponse
    {
        abort_unless($request->user()->can('branding.manage'), 403, 'Forbidden.');
        abort_unless(in_array($type, self::ASSET_TYPES, true), 404);

        $data = $request->validate([
            'file' => [
                'required',
                'file',
                'max:2048',
                'mimetypes:image/png,image/jpeg,image/webp,image/svg+xml,image/x-icon,image/vnd.microsoft.icon',
            ],
        ]);

        $tenant = $request->user()->tenant;
        $file = $data['file'];
        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');
        $column = "{$type}_storage_key";
        $key = "tenants/{$tenant->id}/branding/{$type}.{$extension}";

        if ($tenant->{$column}) {
            Storage::disk(config('filesystems.default'))->delete($tenant->{$column});
        }

        $file->storeAs(dirname($key), basename($key), config('filesystems.default'));
        $tenant->update([$column => $key]);

        return response()->json($this->payload($tenant->fresh()));
    }

    public function destroy(Request $request, string $type): JsonResponse
    {
        abort_unless($request->user()->can('branding.manage'), 403, 'Forbidden.');
        abort_unless(in_array($type, self::ASSET_TYPES, true), 404);

        $tenant = $request->user()->tenant;
        $column = "{$type}_storage_key";

        if ($tenant->{$column}) {
            Storage::disk(config('filesystems.default'))->delete($tenant->{$column});
            $tenant->update([$column => null]);
        }

        return response()->json($this->payload($tenant->fresh()));
    }

    private function payload(Tenant $tenant): array
    {
        $colors = array_merge(self::DEFAULT_COLORS, is_array($tenant->branding) ? $tenant->branding : []);

        return [
            'logo_url' => $this->signedUrl($tenant->logo_storage_key),
            'icon_url' => $this->signedUrl($tenant->icon_storage_key),
            'favicon_url' => $this->signedUrl($tenant->favicon_storage_key),
            'has_logo' => (bool) $tenant->logo_storage_key,
            'has_icon' => (bool) $tenant->icon_storage_key,
            'has_favicon' => (bool) $tenant->favicon_storage_key,
            'colors' => [
                'primary' => $colors['primary'],
                'primaryHover' => $colors['primaryHover'],
                'primarySoft' => $colors['primarySoft'],
                'action' => $colors['action'],
                'actionHover' => $colors['actionHover'],
                'derivedFromLogo' => (bool) ($colors['derivedFromLogo'] ?? false),
            ],
        ];
    }

    private function signedUrl(?string $key): ?string
    {
        if (!$key) {
            return null;
        }

        try {
            return Storage::disk('s3_public')->temporaryUrl($key, now()->addHours(24));
        } catch (\Throwable) {
            try {
                return Storage::disk(config('filesystems.default'))->temporaryUrl($key, now()->addHours(24));
            } catch (\Throwable) {
                return null;
            }
        }
    }
}
