<?php
namespace App\Providers;

use App\Services\MeetingProviders\GoogleMeetProvider;
use App\Services\MeetingProviders\MeetingProviderRegistry;
use App\Services\MeetingProviders\MicrosoftTeamsProvider;
use App\Services\MeetingProviders\ZoomProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MeetingProviderRegistry::class, fn () => new MeetingProviderRegistry([
            'teams' => app(MicrosoftTeamsProvider::class),
            'zoom' => app(ZoomProvider::class),
            'google-meet' => app(GoogleMeetProvider::class),
        ]));
    }

    public function boot(): void {}
}
