<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleAndUserSeeder extends Seeder
{
    public function run(): void
    {
        // Вспомогательная функция: создаёт юзера, если его ещё нет,
        // и проставляет роль + активность (повторный запуск безопасен).
        $mk = function (string $email, string $name, string $role) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('password')]
            );

            // Обновляем роль и активность (на случай, если юзер уже был)
            $user->update([
                'role' => $role,
                'is_active' => true,
            ]);

            return $user;
        };

        $mk('admin@example.com', 'Admin', 'admin');
        $mk('adv@example.com', 'Advertiser', 'advertiser');
        $mk('wm@example.com', 'Webmaster', 'webmaster');
    }
}
