<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class ProcessingJob extends Model { use HasUuids; protected $guarded=[]; protected function casts(): array{return ['started_at'=>'datetime','completed_at'=>'datetime','metadata'=>'array'];} public function meeting(){return $this->belongsTo(Meeting::class);} public function inputArtifact(){return $this->belongsTo(MeetingArtifact::class, 'input_artifact_id');} }
