<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BankMovement;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Datos de la barra superior. Lectura para admin y jugador.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'team_name' => Setting::get('team_name', ['value' => 'GaLo'])['value'],
            // El saldo es la suma del libro del banco, no un número suelto.
            'bank_balance' => BankMovement::balance(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'team_name' => ['required', 'string', 'max:40'],
        ]);

        Setting::put('team_name', ['value' => $data['team_name']]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'actor_label' => $request->user()->currentAccessToken()?->name,
            'action' => 'settings.update',
            'changes' => $data,
            'ip' => $request->ip(),
        ]);

        return $this->index();
    }
}
