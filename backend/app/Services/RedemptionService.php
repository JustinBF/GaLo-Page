<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Redemption;
use App\Models\Reward;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Canjes de la tienda.
 *
 * Solo el admin los registra: como los jugadores comparten una única cuenta,
 * no hay forma de saber quién pide qué, y cualquiera podría gastar los
 * créditos de otro.
 */
class RedemptionService
{
    public function __construct(private readonly CreditService $credits) {}

    /**
     * Descuenta el saldo, consume stock y aplica el ascenso de rango si lo hay.
     */
    public function redeem(
        Member $member,
        Reward $reward,
        int $userId,
        ?string $note = null,
    ): Redemption {
        if (! $reward->is_active) {
            throw ValidationException::withMessages([
                'reward_id' => 'Este premio no esta disponible.',
            ]);
        }

        if (! $reward->isInStock()) {
            throw ValidationException::withMessages([
                'reward_id' => 'Este premio se ha agotado.',
            ]);
        }

        $balance = $reward->currency === 'CE' ? $member->ce_balance : $member->co_balance;

        if ($balance < $reward->cost) {
            throw ValidationException::withMessages([
                'member_id' => "{$member->nick} tiene {$balance} {$reward->currency}"
                    ." y el premio cuesta {$reward->cost}.",
            ]);
        }

        return DB::transaction(function () use ($member, $reward, $userId, $note) {
            $redemption = Redemption::create([
                'member_id' => $member->id,
                'reward_id' => $reward->id,
                // Copia del nombre y del precio: el premio puede cambiar o
                // borrarse y el historial debe seguir teniendo sentido.
                'reward_name' => $reward->name,
                'currency' => $reward->currency,
                'cost_paid' => $reward->cost,
                'status' => 'pendiente',
                'processed_by' => $userId,
                'note' => $note,
            ]);

            $this->credits->post(
                member: $member,
                currency: $reward->currency,
                amount: -$reward->cost,
                reason: 'redemption',
                userId: $userId,
                note: "Canje: {$reward->name}",
                redemptionId: $redemption->id,
            );

            if ($reward->stock !== null) {
                $reward->decrement('stock');
            }

            // Los ascensos de rango del team se compran con CO.
            if ($reward->grants_rank_id !== null) {
                $member->forceFill([
                    'organizer_rank_id' => $reward->grants_rank_id,
                ])->save();
            }

            return $redemption;
        });
    }

    /**
     * Cancela un canje y devuelve los créditos.
     *
     * No borra nada del libro: anade un movimiento de corrección, para que el
     * historial siga explicando de donde sale cada crédito.
     */
    public function cancel(Redemption $redemption, int $userId): Redemption
    {
        if ($redemption->status === 'cancelado') {
            throw ValidationException::withMessages([
                'status' => 'Este canje ya estaba cancelado.',
            ]);
        }

        return DB::transaction(function () use ($redemption, $userId) {
            $member = $redemption->member;

            $this->credits->post(
                member: $member,
                currency: $redemption->currency,
                amount: $redemption->cost_paid,
                reason: 'correction',
                userId: $userId,
                note: "Canje cancelado: {$redemption->reward_name}",
                redemptionId: $redemption->id,
            );

            // Devuelve el stock si el premio sigue existiendo y era limitado.
            $reward = $redemption->reward;
            if ($reward !== null && $reward->stock !== null) {
                $reward->increment('stock');
            }

            $redemption->update(['status' => 'cancelado']);

            return $redemption->fresh();
        });
    }
}
