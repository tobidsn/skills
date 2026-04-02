<?php

declare(strict_types=1);

namespace App\Http\Resources\API\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CustomerGameplayResource extends JsonResource
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
            'code' => $this->code,
            'menu_options' => [
                'main_dish' => MenuOptionResource::collection($this->getMenuOptionsByCategory('main_dish')),
                'side_dish' => MenuOptionResource::collection($this->getMenuOptionsByCategory('side_dish')),
                'drink' => MenuOptionResource::collection($this->getMenuOptionsByCategory('drink')),
            ],
        ];
    }

    /**
     * Get menu options filtered by category
     *
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    private function getMenuOptionsByCategory(string $category)
    {
        if (!$this->relationLoaded('menuOptions')) {
            return collect([]);
        }

        return $this->menuOptions
            ->where('category', $category)
            ->sortBy('order')
            ->values();
    }
}
