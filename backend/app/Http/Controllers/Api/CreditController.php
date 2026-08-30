<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CreditTransactionResource;
use App\Models\AuditLog;
use App\Models\CreditTransaction;
use App\Models\Member;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CreditController extends Controller
{
    public function __construct(private readonly CreditService $credits) {}

    /**
     * Historial de un miembro: de aquí sale el "por qué tengo 340 CE".
     */
    public function history(Request $request, Member $member): AnonymousResourceCollection
    {
        $data = $request->validate([
            'currency' => ['nullable', Rule::in(['CE', 'CO'])],
        ]);

        $query = $member->transactions()
            ->with(['event', 'author'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (isset($data['currency'])) {
            $query->where('currency', $data['currency']);
        }

        return CreditTransactionResource::collection($query->get());
    }

    /**
     * Ajuste manual: suma o resta créditos fuera de un evento.
     */
    public function adjust(Request $request, Member $member): JsonResponse
    {
        $data = $request->validate([
            'currency' => ['required', Rule::in(['CE', 'CO'])],
            // Negativo resta. Cero no tiene sentido como movimiento.
            'amount' => ['required', 'integer', 'not_in:0', 'between:-100000,100000'],
            'note' => ['required', 'string', 'max:200'],
        ], [
            'amount.not_in' => 'La cantidad no puede ser cero.',
            'note.required' => 'Explica el motivo del ajuste.',
        ]);

        $transaction = $this->credits->post(
            member: $member,
            currency: $data['currency'],
            amount: $data['amount'],
            reason: 'manual_adjust',
            userId: $request->user()->id,
            note: $data['note'],
        );

        AuditLog::create([
            'user_id' => $request->user()->id,
            'actor_label' => $request->user()->currentAccessToken()?->name,
            'action' => 'credit.adjust',
            'model_type' => Member::class,
            'model_id' => $member->id,
            'changes' => $data,
            'ip' => $request->ip(),
        ]);

        $member->refresh();

        return response()->json([
            'transaction' => new CreditTransactionResource($transaction),
            'ce_balance' => $member->ce_balance,
            'co_balance' => $member->co_balance,
        ]);
    }

    /**
     * Ultimos movimientos del team, para el dashboard.
     */
    public function recent(): AnonymousResourceCollection
    {
        $transactions = CreditTransaction::query()
            ->with(['member', 'event', 'author'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return CreditTransactionResource::collection($transactions);
    }
}
