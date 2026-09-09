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

        // Current income cycle start from the admin-managed `income_cycle_start_day`
        // setting (see MlmSettingsService::getIncomeCycleStart). Sales and income
        // cards show the current cycle's data, so they stay consistent with the
        // value shown in the app and follow the cycle day if the admin changes it.
        $cycleStart = app(\App\Services\MlmSettingsService::class)->getIncomeCycleStart();

        [$totalMembers, $activeMembers, $totalOrders] = [
            Member::count(),
            Member::where('status', 'ACTIVE')->count(),
            Order::count(),
        ];

        $todaysOrders = Order::whereDate('created_at', $today)
            ->whereNotNull('member_id')
            ->count();

        // Sales + BV for the CURRENT CYCLE (matches the income cards).
        $orderStats = Order::query()
            ->where('created_at', '>=', $cycleStart)
            ->whereNotIn('status', ['CANCELLED'])
            ->whereNotNull('member_id')
            ->selectRaw('COALESCE(SUM(total), 0) as total_sales')
            ->selectRaw('COALESCE(SUM(total_bv), 0) as total_bv')
            ->selectRaw('COUNT(*) as orders_count')
            ->first();

        // Total BV card = COMPLETE (lifetime) volume across all non-cancelled orders.
        // Kept as its own query because it deliberately has no date filter.
        $lifecycleBv = Order::query()
            ->whereNotIn('status', ['CANCELLED'])
            ->whereNotNull('member_id')
            ->sum('total_bv');

        // Trend chart (30-day sales vs BV) is unchanged from before.
        $rangeStart = $today->copy()->subDays($range);
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
                    ->where('created_at', '>=', $cycleStart)
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
                'totalBv' => (float) $lifecycleBv,
                ...$incomeTotals,
            ],
            'topMembers' => $topMembers,
            'salesSeries' => $salesSeries,
        ];
    }
}
