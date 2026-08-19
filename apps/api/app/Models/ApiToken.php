<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class ApiToken extends Model { use HasUuids; protected $guarded=[]; protected $hidden=['token_hash']; protected function casts(): array{return ['last_used_at'=>'datetime','revoked_at'=>'datetime'];} public function user(){return $this->belongsTo(User::class);} }
