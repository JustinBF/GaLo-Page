<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Http\Resources\MemberResource;
use App\Http\Resources\MemberResultResource;
use App\Models\AuditLog;
use App\Models\Member;
use App\Services\ImageStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function __construct(private readonly ImageStore $images) {}

    /**
     * Tabla CE (scope=players) o tabla CO (scope=organizers). Son dos vistas
     * de la misma tabla: un miembro puede jugar y organizar a la vez.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'scope' => ['nullable', Rule::in(['players', 'organizers', 'all'])],
            'include_inactive' => ['nullable', 'boolean'],
        ]);

        $scope = $data['scope'] ?? 'all';

        $query = Member::query()
            ->with(['rank', 'organizerRank'])
            ->withCount([
                'results as top1_count' => fn ($q) => $q->where('position', 1),
                'results as top2_count' => fn ($q) => $q->where('position', 2),
                'results as top3_count' => fn ($q) => $q->where('position', 3),
                'organizedEventsShared',
            ])
            ->withSum('organizedEventsShared', 'prize_value');

        if ($scope === 'players') {
            $query->where('is_player', true)->orderByDesc('ce_balance');
        } elseif ($scope === 'organizers') {
            $query->where('is_organizer', true)->orderByDesc('co_balance');
        } else {
            $query->orderBy('nick');
        }

        // Los miembros inactivos solo los ve el admin, y solo si los pide.
        $wantsInactive = filter_var(
            $data['include_inactive'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );

        if (! $wantsInactive || ! $request->user()->isAdmin()) {
            $query->where('is_active', true);
        }

        return MemberResource::collection($query->get());
    }

    public function show(Member $member): MemberResource
    {
        $member->load(['rank', 'organizerRank']);

        return new MemberResource($member);
    }

    /**
     * Palmarés del miembro: en qué eventos ha estado en el podio y en qué
     * puesto. Lo ve cualquiera autenticado, igual que la tabla.
     */
    public function results(Member $member): AnonymousResourceCollection
    {
        $results = $member->results()
            ->with('event.badges')
            ->join('events', 'events.id', '=', 'event_results.event_id')
            ->orderByDesc('events.held_at')
            ->orderByDesc('events.id')
            ->select('event_results.*')
            ->get();

        return MemberResultResource::collection($results);
    }

    public function store(StoreMemberRequest $request): JsonResponse
    {
        $member = Member::create($request->validated());

        // Sin refresh, los valores por defecto de la BD (is_active, saldos)
        // volverian como null en la respuesta.
        $member->refresh();

        $this->audit($request, 'member.create', $member, $request->validated());

        return (new MemberResource($member->load(['rank', 'organizerRank'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateMemberRequest $request, Member $member): MemberResource
    {
        $member->update($request->validated());

        $this->audit($request, 'member.update', $member, $request->validated());

        return new MemberResource($member->load(['rank', 'organizerRank']));
    }

    /**
     * Desactiva en lugar de borrar: el historial de créditos y de eventos
     * debe sobrevivir a que alguien deje el team.
     */
    public function destroy(Request $request, Member $member): JsonResponse
    {
        $member->update(['is_active' => false]);

        $this->audit($request, 'member.deactivate', $member);

        return response()->json(['message' => 'Miembro desactivado.']);
    }

    public function avatar(Member $member): Response
    {
        return $this->images->respond($member->avatar_mime, $member->avatar_data);
    }

    public function uploadAvatar(Request $request, Member $member): MemberResource
    {
        $request->validate([
            'avatar' => [
                'required',
                'image',
                'mimetypes:'.implode(',', ImageStore::ALLOWED_MIMES),
                'max:'.(ImageStore::MAX_BYTES / 1024),
                'dimensions:max_width=512,max_height=512',
            ],
        ], [
            'avatar.max' => 'La imagen no puede pasar de 200 KB.',
            'avatar.dimensions' => 'La imagen no puede pasar de 512x512 píxeles.',
            'avatar.mimetypes' => 'Formatos aceptados: PNG, JPG o WEBP.',
        ]);

        $encoded = $this->images->encode($request->file('avatar'));

        $member->update([
            'avatar_mime' => $encoded['mime'],
            'avatar_data' => $encoded['data'],
        ]);

        $this->audit($request, 'member.avatar', $member);

        return new MemberResource($member->load(['rank', 'organizerRank']));
    }

    public function deleteAvatar(Request $request, Member $member): MemberResource
    {
        $member->update(['avatar_mime' => null, 'avatar_data' => null]);

        $this->audit($request, 'member.avatar_removed', $member);

        return new MemberResource($member->load(['rank', 'organizerRank']));
    }

    private function audit(
        Request $request,
        string $action,
        Member $member,
        ?array $changes = null,
    ): void {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'actor_label' => $request->user()->currentAccessToken()?->name,
            'action' => $action,
            'model_type' => Member::class,
            'model_id' => $member->id,
            'changes' => $changes,
            'ip' => $request->ip(),
        ]);
    }
}
