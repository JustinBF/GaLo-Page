<?php

namespace App\Http\Resources;

use App\Models\CreditTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreditTransaction
 */
class CreditTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'reason' => $this->reason,
            'note' => $this->note,
            'created_at' => $this->created_at?->toDateTimeString(),
            'member' => $this->whenLoaded('member', fn () => [
                'id' => $this->member->id,
                'nick' => $this->member->nick,
            ]),
            'event' => $this->whenLoaded(
                'event',
                fn () => $this->event ? ['id' => $this->event->id, 'name' => $this->event->name] : null,
            ),
            // Con cuenta de admin compartida, esto es lo único que dice quién fue.
            'by' => $this->whenLoaded('author', fn () => $this->author?->username),
        ];
    }
}
