<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@lindacasa.com.br');
        $password = env('ADMIN_PASSWORD', 'admin12345');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name'              => env('ADMIN_NAME', 'Administrador'),
                'password'          => Hash::make($password),
                'is_admin'          => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info("Admin: {$email} / {$password}");
    }
}
