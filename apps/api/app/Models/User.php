<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable { use HasUuids, Notifiable; protected $guarded=[]; protected $hidden=['password','remember_token']; protected function casts(): array {return ['email_verified_at'=>'datetime','password'=>'hashed'];} public function tenant(): BelongsTo{return $this->belongsTo(Tenant::class);} public function tokens(){return $this->hasMany(ApiToken::class);} }
