<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class TranscriptSegment extends Model { use HasUuids; protected $guarded=[]; protected function casts(): array{return ['confidence'=>'float','metadata'=>'array'];} public function transcriptVersion(){return $this->belongsTo(TranscriptVersion::class);} public function speaker(){return $this->belongsTo(MeetingSpeaker::class, 'speaker_id');} }
