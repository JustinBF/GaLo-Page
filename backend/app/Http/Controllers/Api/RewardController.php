<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRewardRequest;
use App\Http\Requests\UpdateRewardRequest;
use App\Http\Resources\RewardResource;
use App\Models\AuditLog;
use App\Models\Reward;
use App\Services\ImageStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class RewardController extends Controller
{
    public function __construct(private readonly ImageStore $images) {}

    /**
     * Catalogo de una tienda. currency=CE o currency=CO.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'currency' => ['nullable', Rule::in(['CE', 'CO'])],
            'include_inactive' => ['nullable', 'boolean'],
        ]);

        $query = Reward::query()
            ->with('grantsRank')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('cost');

        if (isset($data['currency'])) {
            $query->where('currency', $data['currency']);
        }

        // Los premios retirados solo los ve el admin, y solo si los pide.
        $wantsInactive = filter_var(
            $data['include_inactive'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );

        if (! $wantsInactive || ! $request->user()->isAdmin()) {
            $query->where('is_active', true);
        }

        return RewardResource::collection($query->get());
    }

    public function show(Reward $reward): RewardResource
    {
        return new RewardResource($reward->load('grantsRank'));
    }

    public function store(StoreRewardRequest $request): JsonResponse
    {
        $reward = Reward::create($request->validated());

        // Sin refresh, los valores por defecto de la BD volverian como null.
        $reward->refresh();

        $this->audit($request, 'reward.create', $reward, $request->validated());

        return (new RewardResource($reward->load('grantsRank')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateRewardRequest $request, Reward $reward): RewardResource
    {
        $reward->update($request->validated());

        $this->audit($request, 'reward.update', $reward, $request->validated());

        return new RewardResource($reward->load('grantsRank'));
    }

    /**
     * Retira el premio de la tienda sin borrarlo: los canjes ya hechos
     * siguen apuntando a el.
     */
    public function destroy(Request $request, Reward $reward): JsonResponse
    {
        $reward->update(['is_active' => false]);

        $this->audit($request, 'reward.retire', $reward);

        return response()->json(['message' => 'Premio retirado de la tienda.']);
    }

    public function image(Reward $reward): Response
    {
        return $this->images->respond($reward->image_mime, $reward->image_data);
    }

    public function uploadImage(Request $request, Reward $reward): RewardResource
    {
        $request->validate([
            'image' => [
                'required',
                'image',
                'mimetypes:'.implode(',', ImageStore::ALLOWED_MIMES),
                'max:'.(ImageStore::MAX_BYTES / 1024),
                'dimensions:max_width=512,max_height=512',
            ],
        ], [
            'image.max' => 'La imagen no puede pasar de 200 KB.',
            'image.dimensions' => 'La imagen no puede pasar de 512x512 píxeles.',
            'image.mimetypes' => 'Formatos aceptados: PNG, JPG o WEBP.',
        ]);

        $encoded = $this->images->encode($request->file('image'));

        $reward->update([
            'image_mime' => $encoded['mime'],
            'image_data' => $encoded['data'],
        ]);

        $this->audit($request, 'reward.image', $reward);

        return new RewardResource($reward->load('grantsRank'));
    }

    public function deleteImage(Request $request, Reward $reward): RewardResource
    {
        $reward->update(['image_mime' => null, 'image_data' => null]);

        $this->audit($request, 'reward.image_removed', $reward);

        return new RewardResource($reward->load('grantsRank'));
    }

    private function audit(
        Request $request,
        string $action,
        Reward $reward,
        ?array $changes = null,
    ): void {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'actor_label' => $request->user()->currentAccessToken()?->name,
            'action' => $action,
            'model_type' => Reward::class,
            'model_id' => $reward->id,
            'changes' => $changes,
            'ip' => $request->ip(),
        ]);
    }
}
