<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Jobs\IngestProviderEvent;
use App\Models\ProviderEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class ProviderWebhookController extends Controller { public function __invoke(Request $request,string $provider): JsonResponse { $raw=$request->getContent(); $expected=hash_hmac('sha256',$raw,(string)config('services.provider_webhooks.secret')); abort_unless(hash_equals($expected,(string)$request->header('X-Webhook-Signature')),401,'Invalid webhook signature.'); $externalId=(string)($request->input('event_id') ?: hash('sha256',$raw)); $event=ProviderEvent::firstOrCreate(['provider'=>$provider,'external_event_id'=>$externalId],['event_type'=>(string)$request->input('event_type','unknown'),'payload'=>$request->json()->all(),'status'=>'received']); if($event->wasRecentlyCreated){IngestProviderEvent::dispatch($event->id)->onQueue('meetings');} return response()->json(['accepted'=>true,'duplicate'=>!$event->wasRecentlyCreated],202); } }
