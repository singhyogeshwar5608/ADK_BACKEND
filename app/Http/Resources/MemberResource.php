<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    /** @var array<int, true>|null Member ids that have at least one PAID order (binary tree batch) */
    private static ?array $treePaidMemberIdSet = null;

    /**
     * Preload paid-order lookup for all members in one query (used by GET members/{id}/tree).
     *
     * @param  array<int>  $memberIds
     */
    public static function beginBinaryTreePaidLookup(array $memberIds): void
    {
        if ($memberIds === []) {
            self::$treePaidMemberIdSet = [];

            return;
        }

        $paidIds = Order::query()
            ->whereIn('member_id', $memberIds)
            ->where('payment_status', 'PAID')
            ->pluck('member_id')
            ->unique()
            ->all();

        self::$treePaidMemberIdSet = array_fill_keys($paidIds, true);
    }

    public static function endBinaryTreePaidLookup(): void
    {
        self::$treePaidMemberIdSet = null;
    }

    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'serialNo' => $this->serial_no,
            'memberId' => $this->member_id,
            'fullName' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'profileImage' => $this->profile_image,
            'qrCodeUrl' => $this->qr_code_url,
            'status' => $this->status,
            /** Yellow (pending_payment) until first order with PAID payment; green (paid) after. */
            'binaryTreePurchaseState' => $this->binaryTreePurchaseState(),
            'role' => $this->role,
            'type' => $this->type ?? 'USER',
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
                'teamSize' => $this->calculateTeamSize(),
                'activeTeam' => $this->calculateActiveTeamSize(),
                'inactiveTeam' => $this->calculateInactiveTeamSize(),
                'totalTeamBV' => $this->calculateTotalTeamBV(),
                'directRefs' => $this->stats_direct_refs,
                'lastLoginAt' => $this->last_login_at?->toIso8601String(),
                'leftTeam' => $this->left_team_count ?? 0,
                'rightTeam' => $this->right_team_count ?? 0,
                'leftChild' => $this->left_child_member_id,
                'rightChild' => $this->right_child_member_id,
                'leftBv' => (float) $this->bv_left_leg,
                'rightBv' => (float) $this->bv_right_leg,
            ],
            'kyc' => [
                'bankAccount' => [
                    'number' => $this->bank_account_number,
                    'image' => $this->bank_account_image,
                ],
                'aadharCard' => [
                    'number' => $this->aadhar_number,
                    'image' => $this->aadhar_image,
                ],
                'panCard' => [
                    'number' => $this->pan_number,
                    'image' => $this->pan_image,
                ],
                'nominee' => [
                    'name' => $this->nominee_name,
                    'aadharNumber' => $this->nominee_aadhar_number,
                    'aadharImage' => $this->nominee_aadhar_image,
                ],
                'status' => $this->kyc_status,
                'rejectionReason' => $this->kyc_rejection_reason,
                'verifiedAt' => $this->kyc_verified_at?->toIso8601String(),
            ],
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function binaryTreePurchaseState(): string
    {
        if (self::$treePaidMemberIdSet !== null) {
            return isset(self::$treePaidMemberIdSet[$this->id]) ? 'paid' : 'pending_payment';
        }

        /** @var \App\Models\Member $m */
        $m = $this->resource;

        return $m->hasPaidProductPurchase() ? 'paid' : 'pending_payment';
    }
}
