<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('AdminUserSeeder is for local development only.');
        }

        User::updateOrCreate(
            ['email' => 'admin@blogs.test'],
            [
                'name' => 'Central Admin',
                'password' => Hash::make(env('ADMIN_PASSWORD') ?: 'password'),
                'email_verified_at' => now(),
            ],
        );
    }
}
