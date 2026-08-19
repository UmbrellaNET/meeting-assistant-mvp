<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMeetingArtifact;
use App\Models\Meeting;
use App\Models\MeetingArtifact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ArtifactController extends Controller
{
    public function store(Request $request, Meeting $meeting): JsonResponse
    {
        abort_unless($meeting->tenant_id === $request->user()->tenant_id, 404);
        $data = $request->validate([
            'artifact_type'=>'required|in:audio,video,provider_transcript,uploaded_transcript',
            'file'=>'required|file|max:524288',
        ]);
        $file = $data['file'];
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $id = (string) Str::uuid();
        $key = "tenants/{$meeting->tenant_id}/meetings/{$meeting->id}/originals/{$id}.{$extension}";
        $checksum = hash_file('sha256', $file->getRealPath());
        $file->storeAs(dirname($key), basename($key), config('filesystems.default'));

        $artifact = MeetingArtifact::create([
            'id'=>$id, 'meeting_id'=>$meeting->id, 'artifact_type'=>$data['artifact_type'], 'source'=>'manual_upload',
            'storage_disk'=>config('filesystems.default'), 'storage_key'=>$key, 'original_filename'=>$file->getClientOriginalName(),
            'mime_type'=>$file->getMimeType() ?: 'application/octet-stream', 'file_size'=>$file->getSize(), 'checksum_sha256'=>$checksum,
            'is_original'=>true, 'status'=>'stored', 'metadata'=>['uploaded_by'=>$request->user()->id],
        ]);

        $meeting->update(['status'=>'artifact_received','processing_status'=>'queued']);
        ProcessMeetingArtifact::dispatch($artifact->id)->onQueue('meetings');

        return response()->json(['artifact'=>$artifact,'message'=>'Artifact stored and queued for processing.'], 202);
    }
    public function playback(Request $request, Meeting $meeting, MeetingArtifact $artifact): JsonResponse
    {
        abort_unless($meeting->tenant_id === $request->user()->tenant_id && $artifact->meeting_id === $meeting->id, 404);
        abort_unless(in_array($artifact->artifact_type, ['audio', 'video'], true), 422, 'Only audio and video artifacts can be played.');
        $expiresAt = now()->addMinutes(15);
        $url = Storage::disk('s3_public')->temporaryUrl($artifact->storage_key, $expiresAt);
        return response()->json([
            'url'=>$url, 'expires_at'=>$expiresAt->toIso8601String(), 'mime_type'=>$artifact->mime_type,
            'duration_ms'=>$artifact->duration_ms, 'checksum_sha256'=>$artifact->checksum_sha256,
        ]);
    }
}
