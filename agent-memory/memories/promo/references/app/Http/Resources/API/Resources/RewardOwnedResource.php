<?php

namespace App\Http\Resources\API\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RewardOwnedResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            'image' => $this->image_url ?? 'https://placehold.co/360x346',
            'type' => $this->type ?? null,
            'is_claimed' => $this->is_claimed,
        ];
    }
}
