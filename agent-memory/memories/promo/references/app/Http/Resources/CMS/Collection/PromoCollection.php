<?php

declare(strict_types=1);

namespace App\Http\Resources\CMS\Collection;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class PromoCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($promo) {
                return [
                    'id' => $promo->id,
                    'title' => $promo->title,
                    'category_id' => $promo->category_id,
                    'category' => $promo->category ? [
                        'id' => $promo->category->id,
                        'name' => $promo->category->name,
                    ] : null,
                    'points' => $promo->points,
                    'is_active' => $promo->is_active,
                    'is_reward' => $promo->is_reward,
                    'reward_id' => $promo->reward_id,
                    'max_redeem' => $promo->max_redeem,
                    'image' => $promo->image,
                    'image_detail' => $promo->image_detail,
                    'description' => $promo->description,
                    'tnc' => $promo->tnc,
                    'start_date' => $promo->start_date?->format('Y-m-d'),
                    'end_date' => $promo->end_date?->format('Y-m-d'),
                    'created_by' => $promo->created_by,
                    'created_by_user' => $promo->createdBy ? [
                        'id' => $promo->createdBy->id,
                        'name' => $promo->createdBy->name,
                    ] : null,
                    'updated_by' => $promo->updated_by,
                    'updated_by_user' => $promo->updatedBy ? [
                        'id' => $promo->updatedBy->id,
                        'name' => $promo->updatedBy->name,
                    ] : null,
                    'created_at' => $promo->created_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $promo->updated_at?->format('Y-m-d H:i:s'),
                ];
            }),
            'pagination' => [
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
                'per_page' => $this->perPage(),
                'total' => $this->total(),
                'from' => $this->firstItem(),
                'to' => $this->lastItem(),
            ],
        ];
    }
}
