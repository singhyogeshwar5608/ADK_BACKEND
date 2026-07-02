<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReferralController extends Controller
{
    /**
     * Get member's referral link
     */
    public function getReferralLink(Request $request): JsonResponse
    {
        $member = $request->user();
        
        if (!$member) {
            return response()->json([
                'status' => false,
                'message' => 'Member not authenticated'
            ], 401);
        }

        $referralCode = ReferralService::getReferralCode($member);
        $referralLinkLeft = ReferralService::getReferralLink($member, 'LEFT');
        $referralLinkRight = ReferralService::getReferralLink($member, 'RIGHT');

        return response()->json([
            'status' => true,
            'data' => [
                'referral_link' => $referralLinkLeft,
                'referral_link_left' => $referralLinkLeft,
                'referral_link_right' => $referralLinkRight,
                'referral_code' => $referralCode,
                'member_id' => $member->member_id,
            ],
            'message' => 'Referral link retrieved successfully'
        ]);
    }

    /**
     * Get member's referral statistics
     */
    public function getReferralStats(Request $request): JsonResponse
    {
        $member = $request->user();
        
        if (!$member) {
            return response()->json([
                'status' => false,
                'message' => 'Member not authenticated'
            ], 401);
        }

        $directReferrals = Member::where('referred_by', $member->member_id)->count();
        $totalReferrals = $this->getTotalReferrals($member);

        return response()->json([
            'status' => true,
            'data' => [
                'direct_referrals' => $directReferrals,
                'total_referrals' => $totalReferrals,
                'referral_link' => ReferralService::getReferralLink($member, 'LEFT'),
                'referral_link_left' => ReferralService::getReferralLink($member, 'LEFT'),
                'referral_link_right' => ReferralService::getReferralLink($member, 'RIGHT'),
                'referral_code' => ReferralService::getReferralCode($member),
            ],
            'message' => 'Referral statistics retrieved successfully'
        ]);
    }

    /**
     * Get total referrals (direct + indirect)
     */
    private function getTotalReferrals(Member $member): int
    {
        // This is a simple implementation - you might want to optimize for large trees
        return Member::where('referred_by', 'like', $member->member_id . '%')->count();
    }
}
