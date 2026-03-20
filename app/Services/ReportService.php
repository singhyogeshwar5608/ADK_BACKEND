<?php

namespace App\Services;

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

        $todaysOrders = Order::whereDate('created_at', $today)->count();

        $orderStats = Order::query()
            ->where('created_at', '>=', $rangeStart)
            ->selectRaw('COALESCE(SUM(total), 0) as total_sales')
            ->selectRaw('COALESCE(SUM(total_bv), 0) as total_bv')
            ->selectRaw('COUNT(*) as orders_count')
            ->first();

        $salesSeries = Order::query()
            ->where('created_at', '>=', $rangeStart)
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

        return [
            'totals' => [
                'totalMembers' => $totalMembers,
                'activeMembers' => $activeMembers,
                'totalOrders' => $totalOrders,
                'todaysOrders' => $todaysOrders,
                'totalSales' => (float) ($orderStats->total_sales ?? 0),
                'totalBv' => (float) ($orderStats->total_bv ?? 0),
            ],
            'topMembers' => $topMembers,
            'salesSeries' => $salesSeries,
        ];
    }
}
