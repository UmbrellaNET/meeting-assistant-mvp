<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_summaries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('transcript_version_id')->nullable()->constrained()->nullOnDelete();
            $table->text('executive_summary')->nullable();
            $table->json('quick_summary')->nullable();
            $table->json('decisions')->nullable();
            $table->json('topics')->nullable();
            $table->json('action_items')->nullable();
            $table->json('risks')->nullable();
            $table->json('dependencies')->nullable();
            $table->json('unknowns')->nullable();
            $table->string('status')->default('completed');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_summaries');
    }
};
