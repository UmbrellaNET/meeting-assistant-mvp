<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Put in: apps/api/database/migrations/
// Needs the pgvector extension (available on Neon). 768 must match GeminiClient::DIMENSIONS.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('transcript_chunks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->uuid('transcript_version_id')->nullable();
            $table->unsignedInteger('sequence');
            $table->unsignedInteger('start_ms');
            $table->unsignedInteger('end_ms');
            $table->text('content');          // "Speaker: text" lines, the passage that was embedded
            $table->jsonb('segment_ids');     // transcript segments this passage covers (first one is the link target)
            $table->timestamps();

            $table->index(['meeting_id', 'sequence']);
        });

        DB::statement('ALTER TABLE transcript_chunks ADD COLUMN embedding vector(768)');
        // No ANN index yet: an exact scan is fast at this size. Add an HNSW index later if the table grows large.
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_chunks');
    }
};
