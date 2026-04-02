<?php

declare(strict_types=1);

namespace App\Http\Resources\API\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class GameplayResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource['gameplay']->key,
            'status' => $this->resource['gameplay']->status,
            'service_score' => $this->resource['gameplay']->service_score,
            'score_level' => $this->resource['score_level'],
            'reward' => $this->when(
                isset($this->resource['reward']) && $this->resource['reward'],
                fn() => new RewardOwnedResource($this->resource['reward'])
            ),
            'best_reward' => $this->when(
                isset($this->resource['best_reward']) && $this->resource['best_reward'],
                fn() => new RewardResource($this->resource['best_reward'])
            ),
        ];
    }
}

