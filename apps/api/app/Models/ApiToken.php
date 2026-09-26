<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }

    public function isImpersonation(): bool
    {
        return $this->impersonator_id !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public static function issue(
        User $user,
        string $name,
        ?string $impersonatorId = null,
        ?DateTimeInterface $expiresAt = null,
    ): string {
        $plain = Str::random(64);
        static::create([
            'user_id' => $user->id,
            'name' => $name,
            'token_hash' => hash('sha256', $plain),
            'impersonator_id' => $impersonatorId,
            'expires_at' => $expiresAt,
        ]);

        return $plain;
    }
}
