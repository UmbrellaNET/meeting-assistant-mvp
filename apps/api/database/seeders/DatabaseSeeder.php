<?php
namespace Database\Seeders;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder { public function run(): void { $tenant=Tenant::firstOrCreate(['slug'=>'demo-organisation'],['name'=>'Demo Organisation']); User::firstOrCreate(['email'=>'developer@example.com'],['tenant_id'=>$tenant->id,'name'=>'MVP Developer','password'=>'password','email_verified_at'=>now()]); } }
