<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class ProviderAccount extends Model { use HasUuids; protected $guarded=[]; protected $hidden=['access_token','refresh_token']; protected function casts(): array{return ['access_token'=>'encrypted','refresh_token'=>'encrypted','token_expires_at'=>'datetime','metadata'=>'array'];} public function tenant(){return $this->belongsTo(Tenant::class);} }
