<?php

namespace App\Http\Resources\API\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MemberEnergyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'energy' => $this->energy,
            'points' => $this->points,
            'transaction_id' => $this->transaction_id,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
