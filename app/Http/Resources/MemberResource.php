<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'memberId' => $this->member_id,
            'fullName' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'profileImage' => $this->profile_image,
            'status' => $this->status,
            'role' => $this->role,
            'leg' => $this->leg,
            'placementPath' => $this->placement_path,
            'depth' => $this->depth,
            'sponsorId' => $this->sponsor?->member_id,
            'wallet' => [
                'balance' => (float) $this->wallet_balance,
                'totalEarned' => (float) $this->wallet_total_earned,
            ],
            'bv' => [
                'total' => (float) $this->bv_total,
                'leftLeg' => (float) $this->bv_left_leg,
                'rightLeg' => (float) $this->bv_right_leg,
                'carryForwardLeft' => (float) $this->bv_carry_forward_left,
                'carryForwardRight' => (float) $this->bv_carry_forward_right,
            ],
            'stats' => [
                'teamSize' => $this->stats_team_size,
                'directRefs' => $this->stats_direct_refs,
                'lastLoginAt' => $this->last_login_at?->toIso8601String(),
                'leftTeam' => $this->left_team_count ?? 0,
                'rightTeam' => $this->right_team_count ?? 0,
                'leftChild' => $this->left_child_member_id,
                'rightChild' => $this->right_child_member_id,
                'leftBv' => (float) $this->bv_left_leg,
                'rightBv' => (float) $this->bv_right_leg,
            ],
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
