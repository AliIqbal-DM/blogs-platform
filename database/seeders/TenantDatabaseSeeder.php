<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class TenantDatabaseSeeder extends Seeder
{
    /**
     * Seed a tenant database. Runs inside the tenant context via tenants:seed.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('TenantDatabaseSeeder is for local development only.');
        }

        User::updateOrCreate(
            ['email' => 'writer@blogs.test'],
            [
                'name' => 'Demo Writer',
                'password' => Hash::make(env('WRITER_PASSWORD') ?: 'password'),
                'email_verified_at' => now(),
            ],
        );
    }
}
