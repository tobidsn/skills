<?php

namespace App\Http\Resources\API\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MysteryBoxResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'voucher_code' => $this->voucher_code ?? null,
            'promo' => $this->whenLoaded('promo', function () {
                return [
                    'id' => $this->promo->id,
                    'title' => $this->promo->title,
                    'description' => $this->promo->description,
                    'image' => $this->promo->image_url,
                    'url' => $this->promo->url,
                ];
            }),
            'reward' => $this->whenLoaded('rewardMember', RewardOwnedResource::make($this->rewardMember)),
        ];
    }
}

