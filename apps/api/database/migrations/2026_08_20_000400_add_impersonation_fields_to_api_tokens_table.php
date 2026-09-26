<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->foreignUuid('impersonator_id')->nullable()->after('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at')->nullable()->after('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('impersonator_id');
            $table->dropColumn('expires_at');
        });
    }
};
