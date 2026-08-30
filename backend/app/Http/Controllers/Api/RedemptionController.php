<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RedemptionResource;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\Redemption;
use App\Models\Reward;
use App\Services\RedemptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class RedemptionController extends Controller
{
    public function __construct(private readonly RedemptionService $redemptions) {}

    /**
     * Todos los canjes del team. Lectura para admin y jugador.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['pendiente', 'entregado', 'cancelado'])],
            'currency' => ['nullable', Rule::in(['CE', 'CO'])],
        ]);

        $query = Redemption::query()
            ->with(['member', 'reward', 'processor'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (isset($data['currency'])) {
            $query->where('currency', $data['currency']);
        }

        return RedemptionResource::collection($query->get());
    }

    /**
     * Premios canjeados de un miembro, para su perfil.
     */
    public function forMember(Member $member): AnonymousResourceCollection
    {
        $redemptions = $member->redemptions()
            ->with('reward')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return RedemptionResource::collection($redemptions);
    }

    /**
     * Registra un canje. Solo el admin: con cuenta compartida no se puede
     * saber quién pide, así que el jugador contacta y el admin lo apunta.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'integer', Rule::exists('members', 'id')],
            'reward_id' => ['required', 'integer', Rule::exists('rewards', 'id')],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $member = Member::findOrFail($data['member_id']);
        $reward = Reward::findOrFail($data['reward_id']);

        $redemption = $this->redemptions->redeem(
            member: $member,
            reward: $reward,
            userId: $request->user()->id,
            note: $data['note'] ?? null,
        );

        $this->audit($request, 'redemption.create', $redemption, $data);

        $member->refresh();

        return response()->json([
            'redemption' => new RedemptionResource(
                $redemption->load(['member', 'reward', 'processor']),
            ),
            'ce_balance' => $member->ce_balance,
            'co_balance' => $member->co_balance,
        ], 201);
    }

    /**
     * Marca el premio como entregado (o vuelve a pendiente).
     */
    public function updateStatus(Request $request, Redemption $redemption): RedemptionResource
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pendiente', 'entregado'])],
        ]);

        if ($redemption->status === 'cancelado') {
            abort(422, 'Un canje cancelado no se puede reactivar.');
        }

        $redemption->update([
            'status' => $data['status'],
            'processed_by' => $request->user()->id,
        ]);

        $this->audit($request, 'redemption.status', $redemption, $data);

        return new RedemptionResource(
            $redemption->load(['member', 'reward', 'processor']),
        );
    }

    /**
     * Cancela el canje y devuelve los créditos.
     */
    public function cancel(Request $request, Redemption $redemption): JsonResponse
    {
        $cancelled = $this->redemptions->cancel($redemption, $request->user()->id);

        $this->audit($request, 'redemption.cancel', $cancelled);

        $member = $cancelled->member()->first();

        return response()->json([
            'redemption' => new RedemptionResource(
                $cancelled->load(['member', 'reward', 'processor']),
            ),
            'ce_balance' => $member->ce_balance,
            'co_balance' => $member->co_balance,
        ]);
    }

    private function audit(
        Request $request,
        string $action,
        Redemption $redemption,
        ?array $changes = null,
    ): void {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'actor_label' => $request->user()->currentAccessToken()?->name,
            'action' => $action,
            'model_type' => Redemption::class,
            'model_id' => $redemption->id,
            'changes' => $changes,
            'ip' => $request->ip(),
        ]);
    }
}
