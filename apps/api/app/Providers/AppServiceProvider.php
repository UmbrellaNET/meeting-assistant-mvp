<?php
namespace App\Providers;

use App\Models\Meeting;
use App\Policies\MeetingPolicy;
use App\Services\MeetingProviders\GoogleMeetProvider;
use App\Services\MeetingProviders\MeetingProviderRegistry;
use App\Services\MeetingProviders\MicrosoftTeamsProvider;
use App\Services\MeetingProviders\ZoomProvider;
use App\Support\ActivityContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ActivityContext::class, fn () => new ActivityContext);
        $this->app->singleton(MeetingProviderRegistry::class, fn () => new MeetingProviderRegistry([
            'teams' => app(MicrosoftTeamsProvider::class),
            'zoom' => app(ZoomProvider::class),
            'google-meet' => app(GoogleMeetProvider::class),
        ]));
    }

    public function boot(): void
    {
        Gate::policy(Meeting::class, MeetingPolicy::class);
    }
}
