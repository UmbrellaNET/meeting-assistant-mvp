<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void {
 Schema::create('tenants',function(Blueprint $t){$t->uuid('id')->primary();$t->string('name');$t->string('slug')->unique();$t->timestamps();});
 Schema::create('users',function(Blueprint $t){$t->uuid('id')->primary();$t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();$t->string('name');$t->string('email')->unique();$t->timestamp('email_verified_at')->nullable();$t->string('password');$t->rememberToken();$t->timestamps();});
 Schema::create('api_tokens',function(Blueprint $t){$t->uuid('id')->primary();$t->foreignUuid('user_id')->constrained()->cascadeOnDelete();$t->string('name');$t->char('token_hash',64)->unique();$t->timestamp('last_used_at')->nullable();$t->timestamp('revoked_at')->nullable();$t->timestamps();});
 Schema::create('password_reset_tokens',function(Blueprint $t){$t->string('email')->primary();$t->string('token');$t->timestamp('created_at')->nullable();});
 Schema::create('sessions',function(Blueprint $t){$t->string('id')->primary();$t->uuid('user_id')->nullable()->index();$t->string('ip_address',45)->nullable();$t->text('user_agent')->nullable();$t->longText('payload');$t->integer('last_activity')->index();});
 Schema::create('cache',function(Blueprint $t){$t->string('key')->primary();$t->mediumText('value');$t->integer('expiration');});
 Schema::create('cache_locks',function(Blueprint $t){$t->string('key')->primary();$t->string('owner');$t->integer('expiration');});
 Schema::create('jobs',function(Blueprint $t){$t->bigIncrements('id');$t->string('queue')->index();$t->longText('payload');$t->unsignedTinyInteger('attempts');$t->unsignedInteger('reserved_at')->nullable();$t->unsignedInteger('available_at');$t->unsignedInteger('created_at');});
 Schema::create('job_batches',function(Blueprint $t){$t->string('id')->primary();$t->string('name');$t->integer('total_jobs');$t->integer('pending_jobs');$t->integer('failed_jobs');$t->longText('failed_job_ids');$t->mediumText('options')->nullable();$t->integer('cancelled_at')->nullable();$t->integer('created_at');$t->integer('finished_at')->nullable();});
 Schema::create('failed_jobs',function(Blueprint $t){$t->id();$t->string('uuid')->unique();$t->text('connection');$t->text('queue');$t->longText('payload');$t->longText('exception');$t->timestamp('failed_at')->useCurrent();});
 } public function down(): void { Schema::dropIfExists('failed_jobs');Schema::dropIfExists('job_batches');Schema::dropIfExists('jobs');Schema::dropIfExists('cache_locks');Schema::dropIfExists('cache');Schema::dropIfExists('sessions');Schema::dropIfExists('password_reset_tokens');Schema::dropIfExists('api_tokens');Schema::dropIfExists('users');Schema::dropIfExists('tenants'); } };
