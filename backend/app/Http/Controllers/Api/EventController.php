<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Member;
use App\Services\CoCalculator;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    public function __construct(
        private readonly CoCalculator $coCalculator,
        private readonly CreditService $credits,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $events = Event::query()
            ->with(['organizer', 'results.member'])
            ->orderByDesc('held_at')
            ->orderByDesc('id')
            ->get();

        return EventResource::collection($events);
    }

    public function show(Event $event): EventResource
    {
        return new EventResource($event->load(['organizer', 'results.member']));
    }

    /**
     * Cuanto CO corresponde a un premio según las reglas vigentes.
     * El formulario lo consulta mientras el admin teclea el valor.
     */
    public function suggestCo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prize_value' => ['required', 'integer', 'min:0'],
        ]);

        return response()->json([
            'co_awarded' => $this->coCalculator->suggest($data['prize_value']),
        ]);
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        $event = DB::transaction(
            fn () => $this->persist(new Event, $request->validated(), $request),
        );

        $this->audit($request, 'event.create', $event, $request->validated());

        return (new EventResource($event->load(['organizer', 'results.member'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateEventRequest $request, Event $event): EventResource
    {
        $updated = DB::transaction(
            fn () => $this->persist($event, $request->validated(), $request),
        );

        $this->audit($request, 'event.update', $updated, $request->validated());

        return new EventResource($updated->load(['organizer', 'results.member']));
    }

    /**
     * Borrar un evento retira los créditos que repartio: si no, quedarian
     * saldos que no se pueden justificar con ningún evento.
     */
    public function destroy(Request $request, Event $event): JsonResponse
    {
        DB::transaction(function () use ($event) {
            $this->credits->clearEventTransactions($event);
            $event->results()->delete();
            $event->delete();
        });

        $this->audit($request, 'event.delete', $event);

        return response()->json(['message' => 'Evento eliminado y créditos retirados.']);
    }

    /**
     * Guarda el evento y reconstruye por completo su reparto de créditos.
     *
     * @param  array<string, mixed>  $data
     */
    private function persist(Event $event, array $data, Request $request): Event
    {
        $manualCo = array_key_exists('co_awarded', $data) && $data['co_awarded'] !== null;

        $event->fill([
            'name' => $data['name'],
            'type' => $data['type'],
            'held_at' => $data['held_at'],
            'difficulty' => $data['difficulty'],
            'prize_value' => $data['prize_value'],
            'organizer_id' => $data['organizer_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'co_manual_override' => $manualCo,
            'co_awarded' => $manualCo
                ? $data['co_awarded']
                : $this->coCalculator->suggest($data['prize_value']),
        ])->save();

        // Reparto desde cero: evita duplicar créditos al editar.
        $this->credits->clearEventTransactions($event);
        $event->results()->delete();

        $userId = $request->user()->id;

        foreach ($data['results'] ?? [] as $result) {
            $event->results()->create([
                'member_id' => $result['member_id'],
                'position' => $result['position'],
                'ce_awarded' => $result['ce_awarded'],
            ]);

            if ($result['ce_awarded'] !== 0) {
                $this->credits->post(
                    member: Member::findOrFail($result['member_id']),
                    currency: 'CE',
                    amount: $result['ce_awarded'],
                    reason: 'event_win',
                    userId: $userId,
                    note: "Top {$result['position']} en {$event->name}",
                    eventId: $event->id,
                );
            }
        }

        if ($event->organizer_id && $event->co_awarded !== 0) {
            $this->credits->post(
                member: Member::findOrFail($event->organizer_id),
                currency: 'CO',
                amount: $event->co_awarded,
                reason: 'event_organized',
                userId: $userId,
                note: "Organizó {$event->name}",
                eventId: $event->id,
            );
        }

        return $event->fresh();
    }

    private function audit(
        Request $request,
        string $action,
        Event $event,
        ?array $changes = null,
    ): void {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'actor_label' => $request->user()->currentAccessToken()?->name,
            'action' => $action,
            'model_type' => Event::class,
            'model_id' => $event->id,
            'changes' => $changes,
            'ip' => $request->ip(),
        ]);
    }
}
