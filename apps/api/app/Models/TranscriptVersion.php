<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class TranscriptVersion extends Model { use HasUuids; protected $guarded=[]; protected function casts(): array{return ['is_current'=>'boolean','metadata'=>'array'];} public function meeting(){return $this->belongsTo(Meeting::class);} public function sourceArtifact(){return $this->belongsTo(MeetingArtifact::class, 'source_artifact_id');} public function segments(){return $this->hasMany(TranscriptSegment::class)->orderBy('sequence');} }
