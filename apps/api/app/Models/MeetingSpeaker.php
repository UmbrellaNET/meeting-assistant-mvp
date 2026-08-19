<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class MeetingSpeaker extends Model { use HasUuids; protected $guarded=[]; protected function casts(): array{return ['confirmed_at'=>'datetime','identity_confidence'=>'float'];} public function meeting(){return $this->belongsTo(Meeting::class);} public function segments(){return $this->hasMany(TranscriptSegment::class, 'speaker_id');} }
