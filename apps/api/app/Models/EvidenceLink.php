<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class EvidenceLink extends Model { use HasUuids; protected $guarded=[]; protected function casts(): array{return ['segment_ids'=>'array','metadata'=>'array'];} public function meeting(){return $this->belongsTo(Meeting::class);} public function artifact(){return $this->belongsTo(MeetingArtifact::class, 'recording_artifact_id');} public function transcriptVersion(){return $this->belongsTo(TranscriptVersion::class);} }
