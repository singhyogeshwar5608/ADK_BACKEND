<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Support\Str;

class ReferralService
{
    /**
     * Generate referral code for a member
     */
    public static function generateReferralCode(Member $member): string
    {
        // Use member_id or create custom code
        $baseCode = $member->member_id;
        
        // Or generate custom code
        $customCode = strtoupper(Str::random(8));
        
        return $baseCode; // or $customCode
    }
    
    /**
     * Base URL for the public signup page (query: ref, optional leg=LEFT|RIGHT).
     */
    public static function signupPageBaseUrl(): string
    {
        return rtrim((string) config('app.signup_url'), '/');
    }

    /**
     * Build signup URL with referral code and optional binary leg for new members.
     */
    public static function buildReferralLink(string $referralCode, ?string $leg = null): string
    {
        $url = self::signupPageBaseUrl() . '?ref=' . rawurlencode($referralCode);
        if ($leg !== null && $leg !== '') {
            $legUpper = strtoupper($leg);
            if (in_array($legUpper, ['LEFT', 'RIGHT'], true)) {
                $url .= '&leg=' . $legUpper;
            }
        }

        return $url;
    }

    /**
     * Get referral link for a member (optionally pin LEFT or RIGHT leg for invitees).
     */
    public static function getReferralLink(Member $member, ?string $leg = null): string
    {
        $referralCode = self::getReferralCode($member);

        return self::buildReferralLink($referralCode, $leg);
    }
    
    /**
     * Get referral code for a member
     */
    public static function getReferralCode(Member $member): string
    {
        // Check if member has custom referral_code
        if ($member->referral_code) {
            return $member->referral_code;
        }
        
        // Fallback to member_id
        return $member->member_id;
    }
    
    /**
     * Validate referral code
     */
    public static function validateReferralCode(string $code): ?Member
    {
        return Member::where('member_id', $code)
            ->orWhere('referral_code', $code)
            ->first();
    }
}
