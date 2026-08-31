<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DuesPayment;
use App\Models\Member;
use App\Services\DuesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cuotas semanales de los jugadores.
 *
 * La semana es la ISO (lunes a domingo) y se calcula sola. Se puede
 * consultar cualquier semana pasada para revisar o corregir.
 */
class DuesController extends Controller
{
    public function __construct(private readonly DuesService $dues) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'week' => ['nullable', 'date'],
        ]);

        $weekStart = DuesPayment::weekStart($data['week'] ?? null);

        // Solo jugadores: los organizadores que no juegan no pagan cuota.
        $members = Member::query()
            ->where('is_player', true)
            ->where('is_active', true)
            ->orderBy('nick')
            ->get();

        $payments = DuesPayment::whereDate('week_start', $weekStart)
            ->get()
            ->keyBy('member_id');

        $rows = $members->map(function (Member $member) use ($payments) {
            $payment = $payments->get($member->id);

            return [
                'member' => [
                    'id' => $member->id,
                    'nick' => $member->nick,
                    'has_avatar' => $member->hasAvatar(),
                    'avatar_version' => $member->updated_at?->timestamp,
                ],
                'paid' => $payment !== null,
                'amount' => $payment?->amount,
                'paid_at' => $payment?->created_at?->toDateTimeString(),
            ];
        });

        return response()->json([
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekStart->copy()->addDays(6)->toDateString(),
            'default_amount' => $this->dues->defaultAmount(),
            'rows' => $rows,
        ]);
    }

    /**
     * Marca el check: cobra la cuota y la manda al Banco del Team.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'week' => ['nullable', 'date'],
            // Ajustable en el momento: por defecto, la cuota global.
            'amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
        ]);

        $member = Member::findOrFail($data['member_id']);
        $weekStart = DuesPayment::weekStart($data['week'] ?? null);
        $amount = $data['amount'] ?? $this->dues->defaultAmount();

        $payment = $this->dues->charge(
            $member,
            $weekStart,
            $amount,
            $request->user()->id,
        );

        $this->audit($request, 'dues.charge', [
            'member_id' => $member->id,
            'week_start' => $weekStart->toDateString(),
            'amount' => $amount,
        ]);

        return response()->json([
            'message' => 'Cuota cobrada y enviada al banco.',
            'payment' => [
                'member_id' => $payment->member_id,
                'amount' => $payment->amount,
                'week_start' => $weekStart->toDateString(),
            ],
        ]);
    }

    /**
     * Desmarca el check: retira el cobro y su movimiento del banco.
     */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'week' => ['nullable', 'date'],
        ]);

        $member = Member::findOrFail($data['member_id']);
        $weekStart = DuesPayment::weekStart($data['week'] ?? null);

        $this->dues->revert($member, $weekStart);

        $this->audit($request, 'dues.revert', [
            'member_id' => $member->id,
            'week_start' => $weekStart->toDateString(),
        ]);

        return response()->json(['message' => 'Cobro retirado del banco.']);
    }

    /**
     * Cambia la cuota por defecto. No toca los cobros ya hechos.
     */
    public function updateAmount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:0', 'max:999999999999'],
        ]);

        $this->dues->setDefaultAmount($data['amount']);

        $this->audit($request, 'dues.amount', $data);

        return response()->json(['default_amount' => $data['amount']]);
    }

    private function audit(Request $request, string $action, array $changes): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'actor_label' => $request->user()->currentAccessToken()?->name,
            'action' => $action,
            'changes' => $changes,
            'ip' => $request->ip(),
        ]);
    }
}
