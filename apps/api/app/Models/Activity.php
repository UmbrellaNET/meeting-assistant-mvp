<?php

namespace App\Models;

use App\Jobs\ResolveActivityLocation;
use App\Support\ActivityContext;
use App\Support\ActivitySanitizer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'properties' => 'collection',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Activity $activity): void {
            app(ActivityContext::class)->applyTo($activity);
            $activity->properties = collect(
                app(ActivitySanitizer::class)->sanitize($activity->properties?->toArray() ?? [])
            );
        });

        static::created(function (Activity $activity): void {
            if (! config('activity.location_enabled')) {
                return;
            }

            if (! ActivityContext::isPublicIp($activity->ip_address)) {
                return;
            }

            ResolveActivityLocation::dispatch($activity->id);
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }

    public function locationLabel(): ?string
    {
        $parts = array_values(array_filter([$this->city, $this->region, $this->country]));

        return $parts === [] ? null : implode(', ', $parts);
    }
}
