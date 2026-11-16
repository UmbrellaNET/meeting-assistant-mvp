<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Put in: apps/api/database/migrations/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_up_drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject', 255);
            $table->text('body');
            $table->jsonb('recipients')->nullable();      // addresses selected for sending
            $table->string('status', 10)->default('draft'); // draft | sent
            $table->timestamp('sent_at')->nullable();
            $table->foreignUuid('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('sent_to')->nullable();         // addresses the mail server accepted
            $table->timestamps();

            $table->index(['meeting_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_drafts');
    }
};
