<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('logo_storage_key')->nullable()->after('slug');
            $table->string('icon_storage_key')->nullable()->after('logo_storage_key');
            $table->string('favicon_storage_key')->nullable()->after('icon_storage_key');
            $table->json('branding')->nullable()->after('favicon_storage_key');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'logo_storage_key',
                'icon_storage_key',
                'favicon_storage_key',
                'branding',
            ]);
        });
    }
};
