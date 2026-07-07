<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUser(config('automator.default_admin'), 'Admin');
        $this->seedUser(config('automator.default_operator'), 'Operator');
        $this->seedUser(config('automator.default_viewer'), 'Viewer');
    }

    private function seedUser(array $config, string $role): void
    {
        if (User::where('username', $config['username'])->exists()) {
            return;
        }

        $user = User::create([
            'username' => $config['username'],
            'name' => ucfirst($config['username']),
            'email' => $config['email'],
            'password' => $config['password'],
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);
    }
}
