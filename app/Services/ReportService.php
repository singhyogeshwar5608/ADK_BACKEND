<?php

namespace App\Services;

use App\Models\IncomeTransaction;
use App\Models\Member;
use App\Models\Order;
use Illuminate\Support\Carbon;

class ReportService
{
    public static function dashboardMetrics(int $range): array
    {
        $range = max(1, min($range, 180));
        $today = Carbon::today();
        $rangeStart = $today->copy()->subDays($range);

        [$totalMembers, $activeMembers, $totalOrders] = [
            Member::count(),
            Member::where('status', 'ACTIVE')->count(),
            Order::count(),
        ];

        $todaysOrders = Order::whereDate('created_at', $today)
            ->whereNotNull('member_id')
            ->count();

        $orderStats = Order::query()
            ->where('created_at', '>=', $rangeStart)
            ->whereNotIn('status', ['CANCELLED'])
            ->whereNotNull('member_id')
            ->selectRaw('COALESCE(SUM(total), 0) as total_sales')
            ->selectRaw('COALESCE(SUM(total_bv), 0) as total_bv')
            ->selectRaw('COUNT(*) as orders_count')
            ->first();

        $salesSeries = Order::query()
            ->where('created_at', '>=', $rangeStart)
            ->whereNotIn('status', ['CANCELLED'])
            ->whereNotNull('member_id')
            ->selectRaw('DATE(created_at) as label')
            ->selectRaw('COALESCE(SUM(total), 0) as sales')
            ->selectRaw('COALESCE(SUM(total_bv), 0) as bv')
            ->groupBy('label')
            ->orderBy('label')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'sales' => (float) $row->sales,
                'bv' => (float) $row->bv,
            ])->all();

        $topMembers = Member::query()
            ->orderByDesc('bv_total')
            ->limit(5)
            ->get(['member_id', 'full_name', 'email', 'bv_total', 'bv_left_leg', 'bv_right_leg', 'stats_team_size'])
            ->map(fn (Member $member) => [
                'memberId' => $member->member_id,
                'fullName' => $member->full_name,
                'email' => $member->email,
                'bv' => [
                    'total' => (float) $member->bv_total,
                    'leftLeg' => (float) $member->bv_left_leg,
                    'rightLeg' => (float) $member->bv_right_leg,
                ],
                'stats' => [
                    'teamSize' => $member->stats_team_size,
                ],
            ])->all();

        // Actual income from income_transactions table (each type mapped exactly once)
        $incomeTypes = [
            'selfPurchaseIncome' => ['SELF'],
            'sponsorIncome' => ['SPONSOR'],
            'matchingIncome' => ['MATCHING'],
            'selfRepurchase' => ['REPURCHASE_SELF'],
            'repurchaseMatching' => ['REPURCHASE_MATCHING'],
            'repurchaseAwards' => ['SPONSOR_AWARD'],
            'tourRewards' => ['REWARD', 'REPURCHASE_REWARD'],
            'royalty' => [],
        ];

        $incomeTotals = [];
        foreach ($incomeTypes as $key => $types) {
            if (!empty($types)) {
                $incomeTotals[$key] = (float) IncomeTransaction::whereIn('type', $types)
                    ->where('created_at', '>=', $rangeStart)
                    ->sum('amount');
            } else {
                $incomeTotals[$key] = 0;
            }
        }

        $incomeTotals['totalIncome'] = array_sum($incomeTotals);

        return [
            'totals' => [
                'totalMembers' => $totalMembers,
                'activeMembers' => $activeMembers,
                'inactiveMembers' => max(0, $totalMembers - $activeMembers),
                'pendingMembers' => Member::where('status', 'PENDING')->count(),
                'totalOrders' => $totalOrders,
                'todaysOrders' => $todaysOrders,
                'totalSales' => (float) ($orderStats->total_sales ?? 0),
                'totalBv' => (float) ($orderStats->total_bv ?? 0),
                ...$incomeTotals,
            ],
            'topMembers' => $topMembers,
            'salesSeries' => $salesSeries,
        ];
    }
}
