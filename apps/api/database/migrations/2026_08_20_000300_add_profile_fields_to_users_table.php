<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('surname')->nullable()->after('first_name');
            $table->string('contact_number', 40)->nullable()->after('email');
            $table->string('job_title', 120)->nullable()->after('contact_number');
            $table->string('department', 120)->nullable()->after('job_title');
            $table->string('timezone', 80)->nullable()->after('department');
            $table->text('bio')->nullable()->after('timezone');
            $table->text('avatar_storage_key')->nullable()->after('bio');
        });

        foreach (DB::table('users')->orderBy('id')->get() as $user) {
            $parts = preg_split('/\s+/', trim((string) $user->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $surname = count($parts) > 1 ? array_pop($parts) : '';
            $firstName = implode(' ', $parts);

            DB::table('users')->where('id', $user->id)->update([
                'first_name' => $firstName !== '' ? $firstName : $user->name,
                'surname' => $surname,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'surname',
                'contact_number',
                'job_title',
                'department',
                'timezone',
                'bio',
                'avatar_storage_key',
            ]);
        });
    }
};
