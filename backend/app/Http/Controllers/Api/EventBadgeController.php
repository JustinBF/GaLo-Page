<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventBadge;
use App\Services\ImageStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Insignias personalizadas de cada evento.
 *
 * Se guardan en la base de datos como el resto de imagenes: Render borra el
 * disco en cada despliegue.
 */
class EventBadgeController extends Controller
{
    public function __construct(private readonly ImageStore $images) {}

    /**
     * Publica a proposito: <img src> no puede mandar la cabecera
     * Authorization. Solo expone la imagen.
     */
    public function show(Event $event, string $position): Response
    {
        $badge = $event->badges()
            ->where('position', $position === 'general' ? null : (int) $position)
            ->first();

        return $this->images->respond($badge?->mime, $badge?->data);
    }

    public function upload(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'badge' => [
                'required',
                'image',
                'mimetypes:'.implode(',', ImageStore::ALLOWED_MIMES),
                'max:'.(ImageStore::MAX_BYTES / 1024),
                'dimensions:max_width=512,max_height=512',
            ],
            // Sin position es la insignia general del evento.
            'position' => ['nullable', 'integer', 'between:1,3'],
        ], [
            'badge.max' => 'La insignia no puede pasar de 200 KB.',
            'badge.dimensions' => 'La insignia no puede pasar de 512x512 píxeles.',
            'badge.mimetypes' => 'Formatos aceptados: PNG, JPG o WEBP.',
        ]);

        $encoded = $this->images->encode($request->file('badge'));

        EventBadge::updateOrCreate(
            ['event_id' => $event->id, 'position' => $data['position'] ?? null],
            ['mime' => $encoded['mime'], 'data' => $encoded['data']],
        );

        $this->audit($request, 'event.badge.upload', $event, [
            'position' => $data['position'] ?? null,
        ]);

        return response()->json(['message' => 'Insignia guardada.']);
    }

    public function destroy(Request $request, Event $event, string $position): JsonResponse
    {
        $event->badges()
            ->where('position', $position === 'general' ? null : (int) $position)
            ->delete();

        $this->audit($request, 'event.badge.delete', $event, ['position' => $position]);

        return response()->json(['message' => 'Insignia eliminada.']);
    }

    private function audit(Request $request, string $action, Event $event, array $changes): void
    {
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
