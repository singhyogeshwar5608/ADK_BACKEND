<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'memberSnapshot' => [
                'memberId' => data_get($this->member_snapshot, 'memberId'),
                'fullName' => data_get($this->member_snapshot, 'fullName'),
                'email' => data_get($this->member_snapshot, 'email'),
            ],
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'total' => (float) $this->total,
            'totalBv' => (float) $this->total_bv,
            'couponCode' => $this->coupon_code,
            'status' => $this->status,
            'paymentStatus' => $this->payment_status,
            'paymentMethod' => $this->payment_method,
            'createdAt' => $this->created_at?->toIso8601String(),
            'history' => collect($this->history ?? [])->map(function ($entry) {
                return [
                    'status' => $entry['status'] ?? null,
                    'note' => $entry['note'] ?? null,
                    'changedBy' => $entry['changedBy'] ?? null,
                    'changedAt' => $entry['changedAt'] ?? null,
                ];
            })->all(),
        ];
    }
}
