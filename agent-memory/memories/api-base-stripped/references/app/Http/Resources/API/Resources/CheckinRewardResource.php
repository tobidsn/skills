<?php

namespace App\Http\Resources\API\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CheckinRewardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'day_number' => $this->day_number,
            'type' => $this->type,
            'energy' => $this->energy,
            'score' => $this->score,
            'reward' => $this->whenLoaded('reward', RewardResource::make($this->reward)),
            'is_completed' => $this->is_completed ?? false,
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
        ];
    }
}
