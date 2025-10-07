<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Topic;

class TopicsSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Финансы',
            'Игры',
            'Здоровье',
            'Образование',
            'Туризм',
            'Мобильные приложения',
            'E-commerce',
        ];

        foreach ($names as $name) {
            Topic::firstOrCreate(['name' => $name]);
        }
    }
}
