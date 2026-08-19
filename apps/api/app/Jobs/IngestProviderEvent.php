<?php
namespace App\Jobs;
use App\Models\ProviderEvent;
use App\Services\MeetingProviders\MeetingProviderRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
class IngestProviderEvent implements ShouldQueue { use Dispatchable,InteractsWithQueue,Queueable,SerializesModels; public function __construct(public readonly string $eventId){} public function handle(MeetingProviderRegistry $registry): void { $event=ProviderEvent::findOrFail($this->eventId); $event->update(['status'=>'processing']); try { $registry->for($event->provider)->ingestEvent($event->payload); $event->update(['status'=>'processed','processed_at'=>now()]); } catch(\Throwable $e){ $event->update(['status'=>'failed','error_message'=>mb_substr($e->getMessage(),0,4000)]); throw $e; } } }
