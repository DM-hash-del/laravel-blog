<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Create or update the admin account from the ADMIN_* values in .env.
     */
    public function run(): void
    {
        /** @var array{name: ?string, email: ?string, password: ?string} $admin */
        $admin = config('auth.admin');

        if (blank($admin['email']) || blank($admin['password'])) {
            throw new RuntimeException('Set ADMIN_EMAIL and ADMIN_PASSWORD in .env before seeding the admin account.');
        }

        $user = User::updateOrCreate(
            ['email' => $admin['email']],
            [
                'name' => filled($admin['name']) ? $admin['name'] : 'Admin',
                'password' => $admin['password'],
            ],
        );

        $user->forceFill(['is_admin' => true])->save();
    }
}
