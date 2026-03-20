<?php

namespace App\Services;

use App\Models\Member;

class MemberStatsService
{
    public function handleNewMember(Member $member): void
    {
        if (!$member->placement_path) {
            return;
        }

        $ancestorPaths = $this->ancestorPaths($member->placement_path);

        if (!empty($ancestorPaths)) {
            Member::query()
                ->whereIn('placement_path', $ancestorPaths)
                ->increment('stats_team_size');
        }

        if ($member->sponsor_id) {
            Member::query()
                ->where('id', $member->sponsor_id)
                ->increment('stats_direct_refs');
        }
    }

    private function ancestorPaths(string $path): array
    {
        $segments = explode('.', $path);
        $paths = [];

        while (count($segments) > 1) {
            array_pop($segments);
            $paths[] = implode('.', $segments);
        }

        return $paths;
    }
}
