<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class Meeting extends Model { use HasUuids; protected $guarded=[]; protected function casts(): array{return ['scheduled_start_at'=>'datetime','actual_start_at'=>'datetime','actual_end_at'=>'datetime','metadata'=>'array'];} public function tenant(){return $this->belongsTo(Tenant::class);} public function artifacts(){return $this->hasMany(MeetingArtifact::class);} public function transcriptVersions(){return $this->hasMany(TranscriptVersion::class);} public function currentTranscript(){return $this->hasOne(TranscriptVersion::class)->where('is_current', true);} public function speakers(){return $this->hasMany(MeetingSpeaker::class);} public function processingJobs(){return $this->hasMany(ProcessingJob::class);} }
