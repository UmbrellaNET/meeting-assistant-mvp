<?php

namespace App\Models\Concerns;

use App\Models\Meeting;
use App\Models\Tenant;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait LogsDomainActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName($this->activityLogName())
            ->logOnly($this->activityLogAttributes())
            ->logExcept(['password', 'remember_token', 'token_hash'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn (string $eventName) => $this->activityDescription($eventName));
    }

    public function tapActivity(ActivityContract $activity, string $eventName): void
    {
        if (in_array($eventName, ['created', 'updated', 'deleted', 'restored'], true)) {
            $activity->event = $this->activityEventPrefix().'.'.$eventName;
        }
        $activity->subject_label ??= $this->activitySubjectLabel();
        $activity->tenant_id ??= $this->activityTenantId();
    }

    abstract protected function activityLogName(): string;

    abstract protected function activityEventPrefix(): string;

    /**
     * @return list<string>
     */
    abstract protected function activityLogAttributes(): array;

    protected function activityDescription(string $eventName): string
    {
        $label = $this->activitySubjectLabel();

        return match ($eventName) {
            'created' => "Created {$label}",
            'updated' => "Updated {$label}",
            'deleted' => "Deleted {$label}",
            default => ucfirst($eventName).' '.$label,
        };
    }

    protected function activitySubjectLabel(): string
    {
        foreach (['title', 'name', 'display_name', 'email'] as $attribute) {
            $value = $this->{$attribute} ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return class_basename($this);
    }

    protected function activityTenantId(): ?string
    {
        if ($this instanceof Tenant) {
            return $this->getKey();
        }

        if (array_key_exists('tenant_id', $this->getAttributes())) {
            return $this->tenant_id;
        }

        if (array_key_exists('meeting_id', $this->getAttributes()) && $this->meeting_id) {
            if ($this->relationLoaded('meeting')) {
                return $this->meeting?->tenant_id;
            }

            return Meeting::query()->whereKey($this->meeting_id)->value('tenant_id');
        }

        return null;
    }
}
