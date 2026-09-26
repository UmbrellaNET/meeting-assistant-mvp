<?php

namespace App\Models;

use App\Models\Concerns\LogsDomainActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles, HasUuids, LogsDomainActivity, Notifiable;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function meetingParticipations(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    /**
     * @return array{first_name: string, surname: string}
     */
    public static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) {
            return ['first_name' => '', 'surname' => ''];
        }
        if (count($parts) === 1) {
            return ['first_name' => $parts[0], 'surname' => ''];
        }

        $surname = array_pop($parts);

        return ['first_name' => implode(' ', $parts), 'surname' => $surname];
    }

    public static function composeName(?string $firstName, ?string $surname): string
    {
        return trim(($firstName ?? '').' '.($surname ?? ''));
    }

    public function applyNameParts(string $firstName, string $surname = ''): void
    {
        $this->first_name = $firstName;
        $this->surname = $surname;
        $this->name = self::composeName($firstName, $surname) ?: $firstName;
    }

    public function applyFullName(string $name): void
    {
        $parts = self::splitName($name);
        $this->applyNameParts($parts['first_name'], $parts['surname']);
    }

    protected function activityLogName(): string
    {
        return 'users';
    }

    protected function activityEventPrefix(): string
    {
        return 'user';
    }

    protected function activityLogAttributes(): array
    {
        return [
            'name',
            'first_name',
            'surname',
            'email',
            'contact_number',
            'job_title',
            'department',
            'timezone',
            'bio',
            'avatar_storage_key',
        ];
    }

    public function avatarUrl(): ?string
    {
        $key = $this->avatar_storage_key;
        if (! $key) {
            return null;
        }

        try {
            return Storage::disk('s3_public')->temporaryUrl($key, now()->addHours(24));
        } catch (\Throwable) {
            try {
                return Storage::disk(config('filesystems.default'))->temporaryUrl($key, now()->addHours(24));
            } catch (\Throwable) {
                return null;
            }
        }
    }
}
