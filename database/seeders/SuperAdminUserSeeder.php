<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminUserSeeder extends Seeder
{
    /**
     * Cria ou atualiza o usuário dono global. Senha vem só de env — nunca commitar.
     */
    public function run(): void
    {
        $password = env('HUB_SEED_SUPER_ADMIN_PASSWORD');

        if ($password === null || $password === '') {
            $this->command?->warn(
                'SuperAdminUserSeeder: defina HUB_SEED_SUPER_ADMIN_PASSWORD no .env para criar o super_admin.'
            );

            return;
        }

        $email = env('HUB_SEED_SUPER_ADMIN_EMAIL', 'rapha@raphael-martins.com');

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => env('HUB_SEED_SUPER_ADMIN_NAME', 'Raphael Martins'),
                'password' => Hash::make($password),
                'global_role' => 'super_admin',
                'email_verified_at' => now(),
            ],
        );

        $this->command?->info("SuperAdminUserSeeder: usuário {$email} garantido como super_admin.");
    }
}
