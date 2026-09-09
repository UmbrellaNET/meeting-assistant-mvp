<?php

namespace App\Jobs;

use App\Models\Activity;
use App\Support\ActivityContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stevebauman\Location\Facades\Location;

class ResolveActivityLocation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $activityId) {}

    public function handle(): void
    {
        $activity = Activity::query()->find($this->activityId);
        if (! $activity || $activity->city || $activity->country) {
            return;
        }

        if (! ActivityContext::isPublicIp($activity->ip_address)) {
            return;
        }

        $position = Location::get($activity->ip_address);
        if (! $position) {
            return;
        }

        $activity->forceFill([
            'country' => $position->countryName,
            'region' => $position->regionName,
            'city' => $position->cityName,
        ])->saveQuietly();
    }
}
