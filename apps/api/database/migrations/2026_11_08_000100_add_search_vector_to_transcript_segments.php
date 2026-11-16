<?php

use App\Models\TranscriptSegment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Put in: apps/api/database/migrations/
// Adds a full-text search column (kept up to date by Postgres itself) and a GIN index on transcript segments.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $table = (new TranscriptSegment)->getTable();
        DB::statement("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS search_vector tsvector GENERATED ALWAYS AS (to_tsvector('english', coalesce(\"text\", ''))) STORED");
        DB::statement("CREATE INDEX IF NOT EXISTS {$table}_search_vector_idx ON {$table} USING GIN (search_vector)");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $table = (new TranscriptSegment)->getTable();
        DB::statement("DROP INDEX IF EXISTS {$table}_search_vector_idx");
        DB::statement("ALTER TABLE {$table} DROP COLUMN IF EXISTS search_vector");
    }
};
