<?php

namespace App\Services;

/**
 * Reparte el CO de un evento entre sus organizadores.
 *
 * La division entera casi nunca es exacta, y perder CO por el camino
 * descuadraria la tabla CO. El resto se reparte de uno en uno entre los
 * primeros, asi la suma de las partes es siempre el total.
 */
class CoSplitter
{
    /**
     * @param  list<int>  $memberIds
     * @return array<int, int>  member_id => CO que le toca
     */
    public function split(int $total, array $memberIds): array
    {
        $count = count($memberIds);

        if ($count === 0) {
            return [];
        }

        $base = intdiv($total, $count);
        $remainder = $total - ($base * $count);

        $shares = [];

        foreach (array_values($memberIds) as $index => $memberId) {
            $shares[$memberId] = $base + ($index < $remainder ? 1 : 0);
        }

        return $shares;
    }
}
