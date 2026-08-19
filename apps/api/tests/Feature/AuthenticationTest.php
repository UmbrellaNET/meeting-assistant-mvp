<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class AuthenticationTest extends TestCase { use RefreshDatabase; public function test_user_can_register_and_receive_token(): void { $response=$this->postJson('/api/auth/register',['name'=>'Test User','organisation'=>'Test Org','email'=>'test@example.com','password'=>'very-secure-password']); $response->assertCreated()->assertJsonStructure(['token','user'=>['id','tenant']]); } }
