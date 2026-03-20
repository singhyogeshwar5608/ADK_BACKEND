<?php

namespace App\Services;

use App\Models\Member;
use App\Support\Tree;
use Illuminate\Support\Collection;
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

        $placement = self::attemptPlacement($sponsor, $normalizedLeg, $excludeMemberId);

        if ($placement) {
            return $placement;
        }

        throw ValidationException::withMessages([
            'leg' => 'No available placement found for the requested sponsor.',
        ]);
    }

    protected static function attemptPlacement(Member $root, string $preferredLeg, ?int $excludeMemberId = null): ?array
    {
        $queue = new \SplQueue();
        $queue->enqueue($root);
        $visited = collect([$root->id]);

        while (!$queue->isEmpty()) {
            /** @var Member $current */
            $current = $queue->dequeue();

            foreach (self::legOrder($preferredLeg) as $candidateLeg) {
                $child = Member::query()
                    ->where('sponsor_id', $current->id)
                    ->where('leg', $candidateLeg)
                    ->when($excludeMemberId, fn ($query) => $query->where('id', '!=', $excludeMemberId))
                    ->first();

                if (!$child) {
                    return [
                        'sponsor' => $current,
                        'leg' => $candidateLeg,
                        'path' => Tree::childPath($current->placement_path, $candidateLeg),
                        'depth' => $current->depth + 1,
                    ];
                }

                if (!$visited->contains($child->id)) {
                    $queue->enqueue($child);
                    $visited->push($child->id);
                }
            }
        }

        return null;
    }

    protected static function legOrder(string $preferred): array
    {
        return $preferred === 'LEFT'
            ? ['LEFT', 'RIGHT']
            : ['RIGHT', 'LEFT'];
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
