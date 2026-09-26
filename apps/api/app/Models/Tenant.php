<?php

namespace App\Models;

use App\Models\Concerns\LogsDomainActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasUuids, LogsDomainActivity;

    protected $guarded = [];

    protected $casts = [
        'branding' => 'array',
    ];

    protected function activityLogName(): string
    {
        return 'settings';
    }

    protected function activityEventPrefix(): string
    {
        return 'tenant';
    }

    protected function activityLogAttributes(): array
    {
        return ['name', 'branding', 'logo_storage_key', 'icon_storage_key', 'favicon_storage_key'];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function meetings()
    {
        return $this->hasMany(Meeting::class);
    }
}
