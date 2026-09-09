<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ActivityLogsSchemaUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_upgrade_migration_is_idempotent_on_current_schema(): void
    {
        $this->assertTrue(Schema::hasColumn('activity_logs', 'log_name'));
        $this->assertTrue(Schema::hasColumn('activity_logs', 'impersonator_id'));

        $this->upgradeMigration()->up();

        $this->assertTrue(Schema::hasColumn('activity_logs', 'log_name'));
        $this->assertFalse(Schema::hasColumn('activity_logs', 'actor_user_id'));
    }

    public function test_upgrade_migration_maps_legacy_impersonation_rows(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'meetings@umbrellanet.com')->firstOrFail();
        $target = User::query()->where('email', 'user@umbrellanet.com')->firstOrFail();

        Schema::dropIfExists('activity_logs');
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->foreignUuid('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        $id = (string) Str::uuid();
        DB::table('activity_logs')->insert([
            'id' => $id,
            'tenant_id' => $admin->tenant_id,
            'actor_user_id' => $admin->id,
            'action' => 'impersonation.started',
            'target_user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'metadata' => json_encode(['via' => 'admin']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->upgradeMigration()->up();

        $this->assertTrue(Schema::hasColumn('activity_logs', 'log_name'));
        $this->assertTrue(Schema::hasColumn('activity_logs', 'event'));
        $this->assertTrue(Schema::hasColumn('activity_logs', 'impersonator_id'));
        $this->assertFalse(Schema::hasColumn('activity_logs', 'actor_user_id'));
        $this->assertFalse(Schema::hasColumn('activity_logs', 'action'));
        $this->assertFalse(Schema::hasColumn('activity_logs', 'target_user_id'));
        $this->assertFalse(Schema::hasColumn('activity_logs', 'metadata'));

        $this->assertDatabaseHas('activity_logs', [
            'id' => $id,
            'event' => 'impersonation.started',
            'description' => 'Started impersonation',
            'log_name' => 'users',
            'causer_id' => $target->id,
            'causer_type' => User::class,
            'impersonator_id' => $admin->id,
            'subject_id' => $target->id,
            'subject_type' => User::class,
            'actor_name' => $target->name,
            'actor_email' => $target->email,
        ]);
    }

    private function upgradeMigration(): object
    {
        return require database_path('migrations/2026_09_09_000600_upgrade_activity_logs_to_spatie_schema.php');
    }
}
