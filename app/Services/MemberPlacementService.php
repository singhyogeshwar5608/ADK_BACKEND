<?php

namespace App\Services;

use App\Models\Member;
use App\Support\Tree;
use Illuminate\Validation\ValidationException;

class MemberPlacementService
{
    /**
     * Resolve sponsor/leg placement for a new or moved member.
     */
    public static function resolve(?string $sponsorIdentifier, ?string $leg, ?int $excludeMemberId = null): array
    {
        if (empty($sponsorIdentifier)) {
            $existingRoot = Member::whereNull('sponsor_id')->first();
            if ($existingRoot) {
                throw ValidationException::withMessages([
                    'sponsor_id' => 'Sponsor is required once a root member exists.',
                ]);
            }

            return [
                'sponsor' => null,
                'referrer' => null,
                'leg' => null,
                'path' => Tree::rootPath(),
                'depth' => 0,
            ];
        }

        $normalizedLeg = strtoupper((string) $leg);
        if (!in_array($normalizedLeg, ['LEFT', 'RIGHT'], true)) {
            throw ValidationException::withMessages([
                'leg' => 'Leg must be either LEFT or RIGHT.',
            ]);
        }

        $sponsor = self::findSponsor($sponsorIdentifier);

        if (!$sponsor) {
            throw ValidationException::withMessages([
                'sponsor_id' => 'Sponsor not found.',
            ]);
        }

        // Auto-balance: agar last 4 direct children same leg mein hain to opposite leg force karo
        $lastFourLegs = Member::where('sponsor_id', $sponsor->id)
            ->when($excludeMemberId, fn ($q) => $q->where('id', '!=', $excludeMemberId))
            ->orderBy('id', 'desc')
            ->limit(4)
            ->pluck('leg');

        if ($lastFourLegs->count() === 4 && $lastFourLegs->unique()->count() === 1) {
            $lastFourSameLeg = $lastFourLegs->first();
            if ($lastFourSameLeg === $normalizedLeg) {
                $normalizedLeg = $lastFourSameLeg === 'LEFT' ? 'RIGHT' : 'LEFT';
            }
        }

        $placement = self::attemptPlacement($sponsor, $normalizedLeg, $excludeMemberId);

        if ($placement) {
            $placement['referrer'] = $sponsor;
            return $placement;
        }

        throw ValidationException::withMessages([
            'leg' => 'No available placement found for the requested sponsor.',
        ]);
    }

    protected static function attemptPlacement(Member $root, string $preferredLeg, ?int $excludeMemberId = null): ?array
    {
        $otherLeg = $preferredLeg === 'LEFT' ? 'RIGHT' : 'LEFT';

        // 1. Check if preferred leg slot is free at current node (by placement_path)
        $preferredChildPath = Tree::childPath($root->placement_path, $preferredLeg);
        $preferredChild = Member::query()
            ->where('placement_path', $preferredChildPath)
            ->when($excludeMemberId, fn ($query) => $query->where('id', '!=', $excludeMemberId))
            ->first();

        if (!$preferredChild) {
            return [
                'sponsor' => $root,
                'leg' => $preferredLeg,
                'path' => $preferredChildPath,
                'depth' => $root->depth + 1,
            ];
        }

        // 2. Preferred leg is full → recursively traverse its entire subtree first
        $result = self::attemptPlacement($preferredChild, $preferredLeg, $excludeMemberId);
        if ($result !== null) {
            return $result;
        }

        // 3. Only now check the other leg slot at current node (by placement_path)
        $otherChildPath = Tree::childPath($root->placement_path, $otherLeg);
        $otherChild = Member::query()
            ->where('placement_path', $otherChildPath)
            ->when($excludeMemberId, fn ($query) => $query->where('id', '!=', $excludeMemberId))
            ->first();

        if (!$otherChild) {
            return [
                'sponsor' => $root,
                'leg' => $otherLeg,
                'path' => $otherChildPath,
                'depth' => $root->depth + 1,
            ];
        }

        // 4. Other leg is also full → recursively traverse its subtree
        return self::attemptPlacement($otherChild, $preferredLeg, $excludeMemberId);
    }

    protected static function findSponsor(?string $identifier): ?Member
    {
        if (empty($identifier)) {
            return null;
        }

        return Member::query()
            ->when(is_numeric($identifier), fn ($query) => $query->orWhere('id', $identifier))
            ->orWhere('member_id', $identifier)
            ->first();
    }
}
