<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BankMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Banco del Team.
 *
 * El dinero se mueve fuera de la web; aquí se lleva el registro de quién
 * aportó, cuánto y para que.
 */
class BankController extends Controller
{
    public function index(): JsonResponse
    {
        $movements = BankMovement::query()
            ->with('author')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (BankMovement $movement) => [
                'id' => $movement->id,
                'contributor_name' => $movement->contributor_name,
                'amount' => $movement->amount,
                'description' => $movement->description,
                'created_at' => $movement->created_at?->toDateTimeString(),
                'by' => $movement->author?->username,
            ]);

        return response()->json([
            'balance' => BankMovement::balance(),
            'movements' => $movements,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contributor_name' => ['required', 'string', 'max:60'],
            // Negativo para registrar una salida de dinero.
            'amount' => ['required', 'integer', 'not_in:0', 'between:-999999999999,999999999999'],
            'description' => ['required', 'string', 'max:200'],
        ], [
            'contributor_name.required' => 'Escribe quién aportó el dinero.',
            'amount.not_in' => 'La cantidad no puede ser cero.',
            'description.required' => 'Escribe una descripción breve.',
        ]);

        $movement = BankMovement::create($data + ['created_by' => $request->user()->id]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'actor_label' => $request->user()->currentAccessToken()?->name,
            'action' => 'bank.movement',
            'model_type' => BankMovement::class,
            'model_id' => $movement->id,
            'changes' => $data,
            'ip' => $request->ip(),
        ]);

        return $this->index()->setStatusCode(201);
    }

    /**
     * Borra un apunte equivocado. El saldo se recalcula solo, porque es la
     * suma de los movimientos.
     */
    public function destroy(Request $request, BankMovement $bankMovement): JsonResponse
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'actor_label' => $request->user()->currentAccessToken()?->name,
            'action' => 'bank.delete',
            'model_type' => BankMovement::class,
            'model_id' => $bankMovement->id,
            'changes' => $bankMovement->toArray(),
            'ip' => $request->ip(),
        ]);

        $bankMovement->delete();

        return $this->index();
    }
}
