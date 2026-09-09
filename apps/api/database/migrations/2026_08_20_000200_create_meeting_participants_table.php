<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('participation_role');
            $table->string('status')->default('pending');
            $table->foreignUuid('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['meeting_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        $now = now();
        DB::table('meetings')
            ->whereNotNull('organiser_user_id')
            ->orderBy('id')
            ->get(['id', 'organiser_user_id'])
            ->each(function (object $meeting) use ($now): void {
                DB::table('meeting_participants')->insert([
                    'id' => (string) Str::uuid(),
                    'meeting_id' => $meeting->id,
                    'user_id' => $meeting->organiser_user_id,
                    'participation_role' => 'convenor',
                    'status' => 'accepted',
                    'invited_by_user_id' => $meeting->organiser_user_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_participants');
    }
};
