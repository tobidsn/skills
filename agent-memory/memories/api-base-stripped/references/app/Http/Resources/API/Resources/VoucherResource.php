<?php

namespace App\Http\Resources\API\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherResource extends JsonResource
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
            'title' => $this->promo->title,
            'category_id' => $this->promo->category_id,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->promo->category->id,
                    'name' => $this->promo->category->name,
                    'description' => $this->promo->category->description,
                ];
            }),
            'points' => $this->promo->points,
            'is_active' => $this->promo->is_active,
            'image' => $this->promo->image_url,
            'url' => $this->promo->url,
            'description' => $this->promo->description,
            'tnc' => $this->promo->tnc,
            'voucher_expired_at' => $this->promo->end_date,
            'voucher_code' => $this->voucher_code,
            'reward' => $this->whenLoaded('rewardMember', RewardOwnedResource::make($this->rewardMember)),
        ];
    }
}
