<?php
namespace App\Providers;

use App\Models\Meeting;
use App\Policies\MeetingPolicy;
use App\Services\MeetingProviders\GoogleMeetProvider;
use App\Services\MeetingProviders\MeetingProviderRegistry;
use App\Services\MeetingProviders\MicrosoftTeamsProvider;
use App\Services\MeetingProviders\TeamsGraphClient;
use App\Services\MeetingProviders\ZoomApiClient;
use App\Services\MeetingProviders\ZoomProvider;
use App\Support\ActivityContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ActivityContext::class, fn () => new ActivityContext);

        $this->app->singleton(TeamsGraphClient::class, fn () => new TeamsGraphClient(
            tenantId: config('services.microsoft_teams.tenant_id'),
            clientId: config('services.microsoft_teams.client_id'),
            clientSecret: config('services.microsoft_teams.client_secret'),
        ));

        $this->app->singleton(ZoomApiClient::class, fn () => new ZoomApiClient(
            accountId: config('services.zoom.account_id'),
            clientId: config('services.zoom.client_id'),
            clientSecret: config('services.zoom.client_secret'),
        ));

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