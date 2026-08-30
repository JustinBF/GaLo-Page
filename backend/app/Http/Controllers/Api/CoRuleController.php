<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CoRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reglas premio -> CO. Son datos editables, no logica en el codigo.
 */
class CoRuleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            CoRule::orderByDesc('priority')->orderBy('min_prize_value')->get(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $rule = CoRule::create($this->validated($request));

        $this->audit($request, 'co_rule.create', $rule);

        return response()->json($rule, 201);
    }

    public function update(Request $request, CoRule $coRule): JsonResponse
    {
        $coRule->update($this->validated($request));

        $this->audit($request, 'co_rule.update', $coRule);

        return response()->json($coRule);
    }

    public function destroy(Request $request, CoRule $coRule): JsonResponse
    {
        $this->audit($request, 'co_rule.delete', $coRule);

        $coRule->delete();

        return response()->json(['message' => 'Regla eliminada.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'min_prize_value' => ['required', 'integer', 'min:0'],
            'max_prize_value' => ['nullable', 'integer', 'gte:min_prize_value'],
            'co_amount' => ['required', 'integer', 'min:0', 'max:100000'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['boolean'],
        ], [
            'max_prize_value.gte' => 'El máximo no puede ser menor que el mínimo.',
        ]);
    }

    private function audit(Request $request, string $action, CoRule $rule): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'actor_label' => $request->user()->currentAccessToken()?->name,
            'action' => $action,
            'model_type' => CoRule::class,
            'model_id' => $rule->id,
            'changes' => $rule->toArray(),
            'ip' => $request->ip(),
        ]);
    }
}
