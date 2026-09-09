<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleProvisioner;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'umbrellanet'],
            ['name' => 'UmbrellaNET']
        );

        app(TenantRoleProvisioner::class)->provision($tenant);

        $developer = User::firstOrCreate(
            ['email' => 'meetings@umbrellanet.com'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'UmbrellaNET Developer',
                'first_name' => 'UmbrellaNET',
                'surname' => 'Developer',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );
        if ($developer->wasRecentlyCreated === false && ! $developer->first_name) {
            $developer->applyFullName($developer->name);
            $developer->save();
        }
        if (! $developer->hasRole('super-admin')) {
            $developer->assignRole('super-admin');
        }

        $user = User::firstOrCreate(
            ['email' => 'user@umbrellanet.com'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Umbrella User',
                'first_name' => 'Umbrella',
                'surname' => 'User',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );
        if ($user->wasRecentlyCreated === false && ! $user->first_name) {
            $user->applyFullName($user->name);
            $user->save();
        }
        if (! $user->hasRole('user')) {
            $user->assignRole('user');
        }
    }
}
