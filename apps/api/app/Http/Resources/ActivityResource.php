<?php

namespace App\Http\Resources;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Activity */
class ActivityResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actor = $this->personFrom($this->causer, $this->actor_name, $this->actor_email);
        $target = $this->subjectPerson();

        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'event' => $this->event,
            'action' => $this->event,
            'description' => $this->description,
            'actor' => $actor,
            'target' => $target,
            'actor_name' => $this->actor_name,
            'actor_email' => $this->actor_email,
            'subject_label' => $this->subject_label,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'browser' => $this->browser,
            'browser_version' => $this->browser_version,
            'os' => $this->os,
            'device_type' => $this->device_type,
            'country' => $this->country,
            'region' => $this->region,
            'city' => $this->city,
            'location' => $this->locationLabel(),
            'source' => $this->source,
            'integration' => $this->integration,
            'request_id' => $this->request_id,
            'batch_uuid' => $this->batch_uuid,
            'tenant_id' => $this->tenant_id,
            'impersonator' => $this->impersonator ? [
                'id' => $this->impersonator->id,
                'name' => $this->impersonator->name,
                'email' => $this->impersonator->email,
            ] : null,
            'properties' => $this->properties?->toArray() ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: ?string, name: ?string, email: ?string, type: ?string}|null
     */
    private function personFrom(mixed $model, ?string $name, ?string $email): ?array
    {
        if ($model instanceof User) {
            return [
                'id' => $model->id,
                'name' => $model->name,
                'email' => $model->email,
                'type' => 'user',
            ];
        }

        if ($name || $email) {
            return [
                'id' => is_object($model) && isset($model->id) ? (string) $model->id : null,
                'name' => $name,
                'email' => $email,
                'type' => $model ? class_basename($model) : null,
            ];
        }

        return null;
    }

    /**
     * @return array{id: ?string, name: ?string, email: ?string, type: ?string}|null
     */
    private function subjectPerson(): ?array
    {
        $subject = $this->subject;
        if ($subject instanceof User) {
            return [
                'id' => $subject->id,
                'name' => $subject->name,
                'email' => $subject->email,
                'type' => 'user',
            ];
        }

        if ($this->subject_label || $this->subject_type) {
            return [
                'id' => $this->subject_id,
                'name' => $this->subject_label,
                'email' => $subject->email ?? null,
                'type' => $this->subject_type ? class_basename($this->subject_type) : null,
            ];
        }

        return null;
    }
}
