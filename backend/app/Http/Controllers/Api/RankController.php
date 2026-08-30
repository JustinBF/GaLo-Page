<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Rank;
use App\Services\ImageStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RankController extends Controller
{
    public function __construct(private readonly ImageStore $images) {}

    public function index(): JsonResponse
    {
        $ranks = Rank::orderBy('level')->get()->map(fn (Rank $rank) => [
            'id' => $rank->id,
            'name' => $rank->name,
            'slug' => $rank->slug,
            'level' => $rank->level,
            'scope' => $rank->scope,
            'color_hex' => $rank->color_hex,
            'has_icon' => $rank->icon_mime !== null,
            'icon_version' => $rank->updated_at?->timestamp,
        ]);

        return response()->json($ranks);
    }

    /**
     * Permite renombrar rangos y cambiarles el color sin tocar codigo.
     */
    public function update(Request $request, Rank $rank): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:40'],
            'color_hex' => ['sometimes', 'required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'level' => ['sometimes', 'required', 'integer', 'between:1,50'],
        ], [
            'color_hex.regex' => 'El color debe ir en formato #rrggbb.',
        ]);

        $rank->update($data);

        $this->audit($request, 'rank.update', $rank, $data);

        return response()->json($rank->only([
            'id', 'name', 'slug', 'level', 'scope', 'color_hex',
        ]));
    }

    public function icon(Rank $rank): Response
    {
        return $this->images->respond($rank->icon_mime, $rank->icon_data);
    }

    public function uploadIcon(Request $request, Rank $rank): JsonResponse
    {
        $request->validate([
            'icon' => [
                'required',
                'image',
                'mimetypes:'.implode(',', ImageStore::ALLOWED_MIMES),
                'max:'.(ImageStore::MAX_BYTES / 1024),
                'dimensions:max_width=256,max_height=256',
            ],
        ], [
            'icon.max' => 'El icono no puede pasar de 200 KB.',
            'icon.dimensions' => 'El icono no puede pasar de 256x256 píxeles.',
            'icon.mimetypes' => 'Formatos aceptados: PNG, JPG o WEBP.',
        ]);

        $encoded = $this->images->encode($request->file('icon'));

        $rank->update([
            'icon_mime' => $encoded['mime'],
            'icon_data' => $encoded['data'],
        ]);

        $this->audit($request, 'rank.icon', $rank);

        return response()->json([
            'id' => $rank->id,
            'name' => $rank->name,
            'has_icon' => true,
            'icon_version' => $rank->fresh()->updated_at?->timestamp,
        ]);
    }

    public function deleteIcon(Request $request, Rank $rank): JsonResponse
    {
        $rank->update(['icon_mime' => null, 'icon_data' => null]);

        $this->audit($request, 'rank.icon_removed', $rank);

        return response()->json([
            'id' => $rank->id,
            'name' => $rank->name,
            'has_icon' => false,
            'icon_version' => $rank->fresh()->updated_at?->timestamp,
        ]);
    }

    private function audit(
        Request $request,
        string $action,
        Rank $rank,
        ?array $changes = null,
    ): void {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'actor_label' => $request->user()->currentAccessToken()?->name,
            'action' => $action,
            'model_type' => Rank::class,
            'model_id' => $rank->id,
            'changes' => $changes,
            'ip' => $request->ip(),
        ]);
    }
}
