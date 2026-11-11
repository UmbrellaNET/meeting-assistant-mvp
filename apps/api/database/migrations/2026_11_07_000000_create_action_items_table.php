<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Put in: apps/api/database/migrations/
// Assumes the `meetings` and `users` tables use UUID primary keys (as the ids in your API responses suggest).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignUuid('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('owner_name')->nullable();        // owner as named in the meeting, even if no user matched
            $table->text('title');
            $table->text('context')->nullable();
            $table->date('due_date')->nullable();
            $table->string('due_text')->nullable();          // the deadline as it was said, e.g. "by Friday"
            $table->string('status', 20)->default('open');   // open | in_progress | done | cancelled
            $table->string('priority', 10)->default('medium'); // high | medium | low
            $table->string('source', 10)->default('ai');     // ai | manual
            $table->string('fingerprint', 40)->nullable();   // dedupes AI items when a summary is regenerated
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('edited_at')->nullable();      // set when a person changes the item
            $table->string('external_provider', 20)->nullable(); // reserved for Jira / Asana / Trello sync
            $table->string('external_id')->nullable();
            $table->string('external_url')->nullable();
            $table->timestamps();

            $table->index(['meeting_id', 'status']);
            $table->index(['owner_user_id', 'status']);
            $table->unique(['meeting_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_items');
    }
};
