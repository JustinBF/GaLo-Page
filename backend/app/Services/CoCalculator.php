<?php

namespace App\Services;

use App\Models\CoRule;

/**
 * Traduce el valor del premio de un evento a Créditos de Organizador.
 *
 * Las reglas viven en la tabla co_rules y las edita el admin: por defecto
 * 500k-999k = 50 CO y 1M o más = 100 CO.
 */
class CoCalculator
{
    /**
     * Devuelve el CO sugerido para un premio, o 0 si ninguna regla encaja.
     */
    public function suggest(int $prizeValue): int
    {
        $rule = CoRule::query()
            ->where('is_active', true)
            ->where('min_prize_value', '<=', $prizeValue)
            ->where(function ($q) use ($prizeValue) {
                $q->whereNull('max_prize_value')
                    ->orWhere('max_prize_value', '>=', $prizeValue);
            })
            // Ante solapamientos gana la regla más especifica.
            ->orderByDesc('priority')
            ->orderByDesc('min_prize_value')
            ->first();

        return $rule?->co_amount ?? 0;
    }
}
