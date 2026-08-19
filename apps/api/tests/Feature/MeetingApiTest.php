<?php
namespace Tests\Feature;
use App\Models\ApiToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class MeetingApiTest extends TestCase { use RefreshDatabase; public function test_authenticated_user_can_create_meeting(): void { $tenant=Tenant::create(['name'=>'Acme','slug'=>'acme']);$user=User::create(['tenant_id'=>$tenant->id,'name'=>'Dev','email'=>'dev@example.com','password'=>'password']);$plain=Str::random(64);ApiToken::create(['user_id'=>$user->id,'name'=>'test','token_hash'=>hash('sha256',$plain)]);$this->withToken($plain)->postJson('/api/meetings',['title'=>'Architecture Review','provider'=>'manual'])->assertCreated()->assertJsonPath('title','Architecture Review'); } }
