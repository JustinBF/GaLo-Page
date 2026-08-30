<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RankSeeder::class,
            CoRuleSeeder::class,
            AccountSeeder::class,
        ]);
    }
}
