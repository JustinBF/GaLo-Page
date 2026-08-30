<?php

namespace Database\Seeders;

use App\Models\CoRule;
use Illuminate\Database\Seeder;

/**
 * Reglas de conversion premio -> CO. Editables desde el panel de admin,
 * no están codificadas en la logica.
 */
class CoRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            [
                'label' => 'Premio de 500k a 999k',
                'min_prize_value' => 500_000,
                'max_prize_value' => 999_999,
                'co_amount' => 50,
                'priority' => 10,
            ],
            [
                'label' => 'Premio de 1M o más',
                'min_prize_value' => 1_000_000,
                'max_prize_value' => null,
                'co_amount' => 100,
                'priority' => 20,
            ],
        ];

        foreach ($rules as $rule) {
            CoRule::updateOrCreate(['label' => $rule['label']], $rule);
        }
    }
}
