<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class MeetingArtifact extends Model { use HasUuids; protected $guarded=[]; protected function casts(): array{return ['metadata'=>'array','is_original'=>'boolean'];} public function meeting(){return $this->belongsTo(Meeting::class);} public function transcriptVersions(){return $this->hasMany(TranscriptVersion::class, 'source_artifact_id');} }
