<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AdkEventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'leaderName' => $this->leader_name,
            'meetingDate' => $this->meeting_date?->toDateString(),
            'meetingTime' => $this->meeting_time,
            'storeName' => $this->store_name,
            'address' => $this->address,
            'state' => $this->state,
            'city' => $this->city,
            'leaderMobile' => $this->leader_mobile,
            'storeMobile' => $this->store_mobile,
            'notes' => $this->notes,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
