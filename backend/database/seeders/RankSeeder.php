<?php

namespace Database\Seeders;

use App\Models\Rank;
use Illuminate\Database\Seeder;

class RankSeeder extends Seeder
{
    public function run(): void
    {
        $ranks = [
            ['name' => 'Minino',        'slug' => 'minino',        'level' => 1, 'color_hex' => '#9ca3af'],
            ['name' => 'Meowth',        'slug' => 'meowth',        'level' => 2, 'color_hex' => '#facc15'],
            ['name' => 'Persian',       'slug' => 'persian',       'level' => 3, 'color_hex' => '#fb923c'],
            ['name' => 'Gatos Sombra',  'slug' => 'gatos-sombra',  'level' => 4, 'color_hex' => '#8b5cf6'],
            ['name' => 'Gran Felino',   'slug' => 'gran-felino',   'level' => 5, 'color_hex' => '#06b6d4'],
            ['name' => 'Gato Alpha',    'slug' => 'gato-alpha',    'level' => 6, 'color_hex' => '#ef4444'],
        ];

        foreach ($ranks as $rank) {
            Rank::updateOrCreate(
                ['slug' => $rank['slug']],
                $rank + ['scope' => 'both'],
            );
        }
    }
}
