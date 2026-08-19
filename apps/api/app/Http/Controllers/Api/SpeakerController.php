<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingSpeaker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class SpeakerController extends Controller { public function update(Request $request, Meeting $meeting, MeetingSpeaker $speaker): JsonResponse { abort_unless($meeting->tenant_id===$request->user()->tenant_id && $speaker->meeting_id===$meeting->id,404); $data=$request->validate(['display_name'=>'required|string|max:160']); $speaker->update(['display_name'=>$data['display_name'],'identity_status'=>'confirmed','identity_confidence'=>1,'confirmed_by'=>$request->user()->id,'confirmed_at'=>now()]); return response()->json($speaker); } }
