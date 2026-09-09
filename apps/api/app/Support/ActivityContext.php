<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\ApiToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ActivityContext
{
    public ?string $tenantId = null;

    public ?User $causer = null;

    public ?User $impersonator = null;

    public ?string $ipAddress = null;

    public ?string $userAgent = null;

    public ?string $browser = null;

    public ?string $browserVersion = null;

    public ?string $os = null;

    public ?string $deviceType = null;

    public ?string $requestId = null;

    public string $source = 'api';

    public ?string $integration = null;

    public function reset(): static
    {
        $this->tenantId = null;
        $this->causer = null;
        $this->impersonator = null;
        $this->ipAddress = null;
        $this->userAgent = null;
        $this->browser = null;
        $this->browserVersion = null;
        $this->os = null;
        $this->deviceType = null;
        $this->requestId = null;
        $this->source = 'api';
        $this->integration = null;

        return $this;
    }

    public function capture(Request $request): static
    {
        $this->reset();

        $this->ipAddress = $request->ip();
        $this->userAgent = $request->userAgent();
        $this->requestId = (string) ($request->headers->get('X-Request-Id') ?: Str::uuid());

        $parsed = app(UserAgentInspector::class)->parse($this->userAgent);
        $this->browser = $parsed['browser'];
        $this->browserVersion = $parsed['browser_version'];
        $this->os = $parsed['os'];
        $this->deviceType = $parsed['device_type'];

        $user = $request->user();
        if ($user instanceof User) {
            $this->causer = $user;
            $this->tenantId = $user->tenant_id;
        }

        $token = $request->attributes->get('apiToken');
        if ($token instanceof ApiToken && $token->isImpersonation()) {
            $this->impersonator = $token->impersonator;
        }

        if ($request->is('api/webhooks/*') || $request->is('webhooks/*')) {
            $this->source = 'webhook';
            $provider = $request->route('provider');
            $this->integration = is_string($provider) ? $provider : null;
        }

        return $this;
    }

    public function forSystem(?string $tenantId = null): static
    {
        $this->reset();
        $this->source = 'system';
        $this->tenantId = $tenantId;

        return $this;
    }

    public function forIntegration(string $name, ?string $tenantId = null): static
    {
        $this->reset();
        $this->source = 'integration';
        $this->integration = $name;
        $this->tenantId = $tenantId;

        return $this;
    }

    public function authenticate(User $user, ?ApiToken $token = null): static
    {
        $this->causer = $user;
        $this->tenantId ??= $user->tenant_id;
        if ($token?->isImpersonation()) {
            $this->impersonator = $token->impersonator;
        }

        return $this;
    }

    public function causedBy(?User $user): static
    {
        $this->causer = $user;
        if ($user && ! $this->tenantId) {
            $this->tenantId = $user->tenant_id;
        }

        return $this;
    }

    public function forTenant(?string $tenantId): static
    {
        $this->tenantId = $tenantId;

        return $this;
    }

    public function applyTo(Activity $activity): void
    {
        $activity->tenant_id ??= $this->resolveTenantId($activity);
        $activity->ip_address ??= $this->ipAddress;
        $activity->user_agent ??= $this->userAgent;
        $activity->browser ??= $this->browser;
        $activity->browser_version ??= $this->browserVersion;
        $activity->os ??= $this->os;
        $activity->device_type ??= $this->deviceType;
        $activity->request_id ??= $this->requestId;
        $activity->source ??= $this->source;
        $activity->integration ??= $this->integration;
        $activity->impersonator_id ??= $this->impersonator?->id;

        if (! $activity->causer_id && $this->causer) {
            $activity->causer()->associate($this->causer);
        }

        $actor = $activity->causer instanceof User ? $activity->causer : $this->causer;
        if ($actor instanceof User) {
            $activity->actor_name ??= $actor->name;
            $activity->actor_email ??= $actor->email;
            $activity->tenant_id ??= $actor->tenant_id;
        }

        if (! $activity->subject_label && $activity->subject) {
            $activity->subject_label = $this->labelFor($activity->subject);
        }
    }

    public static function isPublicIp(?string $ip): bool
    {
        if (! $ip) {
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    protected function resolveTenantId(Activity $activity): ?string
    {
        if ($this->tenantId) {
            return $this->tenantId;
        }

        $subject = $activity->subject;
        if ($subject instanceof Tenant) {
            return $subject->id;
        }
        if ($subject && isset($subject->tenant_id)) {
            return $subject->tenant_id;
        }

        $causer = $activity->causer;
        if ($causer instanceof User) {
            return $causer->tenant_id;
        }

        return $this->causer?->tenant_id;
    }

    protected function labelFor(mixed $subject): ?string
    {
        if (! is_object($subject)) {
            return null;
        }

        foreach (['title', 'name', 'display_name', 'email', 'event_type'] as $attribute) {
            $value = $subject->{$attribute} ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return class_basename($subject);
    }
}
