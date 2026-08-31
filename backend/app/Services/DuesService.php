<?php

namespace App\Services;

use App\Models\BankMovement;
use App\Models\DuesPayment;
use App\Models\Member;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cuotas semanales de los jugadores.
 *
 * Marcar el check cobra: crea el pago y su movimiento en el Banco del Team
 * en la misma transaccion. Desmarcarlo retira ambos, para que el saldo del
 * banco siempre cuadre con los cobros registrados.
 */
class DuesService
{
    /** Importe por defecto, editable desde Ajustes. */
    public function defaultAmount(): int
    {
        return (int) (Setting::get('dues_amount', ['amount' => 0])['amount'] ?? 0);
    }

    public function setDefaultAmount(int $amount): void
    {
        Setting::put('dues_amount', ['amount' => $amount]);
    }

    /**
     * Cobra la cuota de un jugador para una semana. Si ya estaba cobrada,
     * ajusta el importe en lugar de duplicar el cobro.
     */
    public function charge(
        Member $member,
        Carbon $weekStart,
        int $amount,
        ?int $userId = null,
    ): DuesPayment {
        return DB::transaction(function () use ($member, $weekStart, $amount, $userId) {
            $existing = DuesPayment::where('member_id', $member->id)
                ->whereDate('week_start', $weekStart)
                ->first();

            if ($existing) {
                $this->clearMovement($existing);
            }

            $movement = BankMovement::create([
                'contributor_name' => $member->nick,
                'amount' => $amount,
                'description' => 'Cuota semana del '.$weekStart->toDateString(),
                'created_by' => $userId,
            ]);

            $payment = DuesPayment::updateOrCreate(
                ['member_id' => $member->id, 'week_start' => $weekStart],
                [
                    'amount' => $amount,
                    'bank_movement_id' => $movement->id,
                    'created_by' => $userId,
                ],
            );

            return $payment->fresh();
        });
    }

    /**
     * Deshace el cobro: quita el pago y su movimiento del banco.
     */
    public function revert(Member $member, Carbon $weekStart): void
    {
        DB::transaction(function () use ($member, $weekStart) {
            $payment = DuesPayment::where('member_id', $member->id)
                ->whereDate('week_start', $weekStart)
                ->first();

            if (! $payment) {
                return;
            }

            $this->clearMovement($payment);
            $payment->delete();
        });
    }

    /**
     * Jugadores con la cuota pendiente de la semana en curso.
     *
     * @param  list<int>  $memberIds
     * @return list<int>
     */
    public function unpaidAmong(array $memberIds, ?Carbon $weekStart = null): array
    {
        if ($memberIds === []) {
            return [];
        }

        $weekStart ??= DuesPayment::weekStart();

        $paid = DuesPayment::whereIn('member_id', $memberIds)
            ->whereDate('week_start', $weekStart)
            ->pluck('member_id')
            ->all();

        return array_values(array_diff($memberIds, $paid));
    }

    private function clearMovement(DuesPayment $payment): void
    {
        $payment->bankMovement?->delete();
        $payment->forceFill(['bank_movement_id' => null])->save();
    }
}
