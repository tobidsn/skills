<?php

namespace App\Http\Resources\API\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MemberCheckinResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->when($this->id, $this->id),
            'day_number' => $this->day_number ?? $this->checkinReward->day_number ?? null,
            'type' => $this->checkinReward->type ?? null,
            'energy_earned' => $this->energy,
            'score_earned' => $this->score,
            'reward' => $this->when(
                $this->relationLoaded('rewardMember') && $this->rewardMember,
                fn () => RewardOwnedResource::make($this->rewardMember)
            ),
            'next_day' => $this->when(
                $this->day_number,
                fn () => min($this->day_number + 1, 30)
            ),
            'created_at' => $this->when(
                $this->created_at,
                fn () => $this->created_at?->format('Y-m-d H:i:s')
            ),
        ];
    }
}

