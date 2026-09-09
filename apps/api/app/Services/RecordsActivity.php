<?php

namespace App\Services;

use App\Enums\ActivityEvent;
use App\Models\Activity;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ActivitySanitizer;
use Illuminate\Database\Eloquent\Model;

class RecordsActivity
{
    protected ActivityEvent|string|null $event = null;

    protected ?string $logName = null;

    protected ?User $causer = null;

    protected bool $anonymous = false;

    protected ?Model $subject = null;

    protected ?string $tenantId = null;

    protected array $properties = [];

    protected ?string $source = null;

    protected ?string $integration = null;

    protected ?User $impersonator = null;

    protected ?string $description = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function fromIntegration(string $name): static
    {
        return static::make()->integration($name)->source('integration');
    }

    public function event(ActivityEvent|string $event): static
    {
        $this->event = $event;

        if ($event instanceof ActivityEvent) {
            $this->logName ??= $event->logName();
            $this->description ??= $event->description();
        }

        return $this;
    }

    public function inLog(string $logName): static
    {
        $this->logName = $logName;

        return $this;
    }

    public function causedBy(?User $user): static
    {
        $this->causer = $user;
        $this->anonymous = $user === null;
        if ($user) {
            $this->tenantId ??= $user->tenant_id;
        }

        return $this;
    }

    public function performedOn(?Model $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function onTenant(Tenant|string|null $tenant): static
    {
        $this->tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function withProperties(array $properties): static
    {
        $this->properties = array_merge($this->properties, $properties);

        return $this;
    }

    public function source(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function integration(?string $name): static
    {
        $this->integration = $name;

        return $this;
    }

    public function impersonatedBy(?User $user): static
    {
        $this->impersonator = $user;

        return $this;
    }

    public function log(?string $description = null): ?Activity
    {
        $event = $this->event instanceof ActivityEvent ? $this->event->value : $this->event;
        $description ??= $this->description ?? ($event ?: 'Activity');
        $properties = app(ActivitySanitizer::class)->sanitizeArray($this->properties);

        $logger = activity($this->logName ?? 'default')
            ->event($event ?? 'logged')
            ->withProperties($properties)
            ->tap(function (Activity $activity): void {
                if ($this->tenantId) {
                    $activity->tenant_id = $this->tenantId;
                }
                if ($this->source) {
                    $activity->source = $this->source;
                }
                if ($this->integration) {
                    $activity->integration = $this->integration;
                }
                if ($this->impersonator) {
                    $activity->impersonator_id = $this->impersonator->id;
                }
                if ($this->subject) {
                    $activity->subject_label ??= $this->subjectLabel($this->subject);
                }
            });

        if ($this->anonymous) {
            $logger->causedByAnonymous();
        } elseif ($this->causer) {
            $logger->causedBy($this->causer);
        }

        if ($this->subject) {
            $logger->performedOn($this->subject);
        }

        $activity = $logger->log($description);

        return $activity instanceof Activity ? $activity : null;
    }

    protected function subjectLabel(Model $subject): string
    {
        foreach (['title', 'name', 'display_name', 'email', 'event_type'] as $attribute) {
            $value = $subject->{$attribute} ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return class_basename($subject);
    }
}
