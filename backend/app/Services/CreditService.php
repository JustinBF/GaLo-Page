<?php

namespace App\Services;

use App\Models\CreditTransaction;
use App\Models\Event;
use App\Models\Member;

/**
 * Único punto por el que se mueven créditos.
 *
 * El saldo real es la suma de credit_transactions; los campos ce_balance y
 * co_balance de members son solo una cache para que las tablas carguen
 * rapido. Nadie debe escribirlos a mano fuera de aquí.
 */
class CreditService
{
    /**
     * Registra un movimiento y deja el saldo cacheado al día.
     */
    public function post(
        Member $member,
        string $currency,
        int $amount,
        string $reason,
        ?int $userId = null,
        ?string $note = null,
        ?int $eventId = null,
        ?int $redemptionId = null,
    ): CreditTransaction {
        $transaction = CreditTransaction::create([
            'member_id' => $member->id,
            'currency' => $currency,
            'amount' => $amount,
            'reason' => $reason,
            'event_id' => $eventId,
            'redemption_id' => $redemptionId,
            'note' => $note,
            'created_by' => $userId,
        ]);

        $this->recalculate($member);

        return $transaction;
    }

    /**
     * Borra los movimientos que generó un evento.
     *
     * Al editar un evento se recalcula todo su reparto desde cero: es más
     * fiable que ir encadenando correcciones, y la edicion queda en
     * audit_logs de todas formas. Nunca toca ajustes manuales ni canjes.
     */
    public function clearEventTransactions(Event $event): void
    {
        $affected = CreditTransaction::where('event_id', $event->id)
            ->pluck('member_id')
            ->unique();

        CreditTransaction::where('event_id', $event->id)->delete();

        Member::whereIn('id', $affected)->get()->each(
            fn (Member $member) => $this->recalculate($member),
        );
    }

    /**
     * Reconstruye el saldo cacheado a partir del libro de transacciones.
     */
    public function recalculate(Member $member): void
    {
        $sums = CreditTransaction::where('member_id', $member->id)
            ->selectRaw('currency, COALESCE(SUM(amount), 0) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency');

        $member->forceFill([
            'ce_balance' => (int) ($sums['CE'] ?? 0),
            'co_balance' => (int) ($sums['CO'] ?? 0),
        ])->save();
    }
}
