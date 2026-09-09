<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\IncomeTransaction;
use App\Models\Member;
use App\Models\MonthlyIncomeSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncomeHistoryController extends Controller
{
    public function myHistory(): JsonResponse
    {
        $member = auth()->user();
        if (!$member instanceof Member) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json(['history' => $this->buildHistory($member)]);
    }

    public function memberHistory(Member $member): JsonResponse
    {
        return response()->json([
            'history' => $this->buildHistory($member),
            'qrCodeImage' => $member->qr_code_image,
            'qrCodeUrl' => $member->qr_code_url,
        ]);
    }

    /**
     * Attach the 5% Monthly Tier DEBIT for each member, per income cycle.
     *
     * Snapshots already carry the tier CREDIT as `downline_monthly_sponsor`, but
     * the tier DEBIT (the 5% taken from the member's own income) lives only in
     * wallet_transactions (context MONTHLY_TIER_DEDUCTION).
     *
     * The debit's `created_at` is NOT reliable for bucketing: the process writes
     * it as now() when the command runs (e.g. an August income's 5% debit lands at
     * 2026-09-01 05:30 when the command runs on the 1st). Instead we bucket by the
     * `meta.month` value on the debit, which correctly identifies the income cycle
     * it belongs to (2026-07 = July cycle, 2026-08 = August cycle, ...). This stays
     * correct regardless of the admin-managed `income_cycle_start_day` setting.
     *
     * `total` in the response is the snapshot total minus the tier debit, so any
     * screen (admin Income History, profile month list) that shows the total shows
     * the net payable amount as the wallet does.
     */
    private function buildHistory(Member $member): array
    {
        $snapshots = MonthlyIncomeSnapshot::where('member_id', $member->id)
            ->orderBy('year_month', 'desc')
            ->get();

        $debitsByMonth = app(\App\Services\MlmSettingsService::class)
            ->getMonthlyTierDebitByCycle($member->id);

        $result = [];
        foreach ($snapshots as $snapshot) {
            $item = $snapshot->toArray();
            $tierDebit = round($debitsByMonth[$snapshot->year_month] ?? 0.0, 2);
            $item['tier_debit'] = $tierDebit;
            // Net payable total, consistent with the wallet screen.
            $item['total'] = round((float) $item['total'] - $tierDebit, 2);
            $result[] = $item;
        }

        return $result;
    }

    public function searchMembers(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json(['members' => []]);
        }

        $members = Member::where('member_id', 'like', "%{$q}%")
            ->orWhere('full_name', 'like', "%{$q}%")
            ->limit(20)
            ->get(['id', 'member_id', 'full_name']);

        return response()->json(['members' => $members]);
    }

    public function currentIncome(Member $member): JsonResponse
    {
        // Income cycle start comes from the admin-managed `income_cycle_start_day`
        // setting (see MlmSettingsService::getIncomeCycleStart), replacing the old
        // temporary subMonth() hack so each month shows only its own income.
        $cycleStart = app(\App\Services\MlmSettingsService::class)->getIncomeCycleStart();

        $incomeQuery = fn($type) => IncomeTransaction::where('member_id', $member->id)
            ->where('type', $type)
            ->where('created_at', '>=', $cycleStart);

        $directIncome = (float) ($member->incomeLedger()
            ->where('type', 'direct')
            ->where('created_at', '>=', $cycleStart)
            ->sum('amount') ?? 0);

        return response()->json([
            'income' => [
                'direct' => $directIncome,
                'self_purchase' => (float) ($incomeQuery('SELF')->sum('amount') ?? 0),
                'self_repurchase' => (float) ($incomeQuery('REPURCHASE_SELF')->sum('amount') ?? 0),
                'sponsor' => (float) ($incomeQuery('SPONSOR')->sum('amount') ?? 0),
                'sponsor_award_kit' => (float) ($incomeQuery('SPONSOR_AWARD')->sum('amount') ?? 0),
                'matching' => (float) ($incomeQuery('MATCHING')->sum('amount') ?? 0),
                'repurchase_matching' => (float) ($incomeQuery('REPURCHASE_MATCHING')->sum('amount') ?? 0),
                'downline_monthly_sponsor' => (float) $member->downline_monthly_sponsor_income,
                'total' => $directIncome
                    + (float) ($incomeQuery('SELF')->sum('amount') ?? 0)
                    + (float) ($incomeQuery('REPURCHASE_SELF')->sum('amount') ?? 0)
                    + (float) ($incomeQuery('SPONSOR')->sum('amount') ?? 0)
                    + (float) ($incomeQuery('SPONSOR_AWARD')->sum('amount') ?? 0)
                    + (float) ($incomeQuery('MATCHING')->sum('amount') ?? 0)
                    + (float) ($incomeQuery('REPURCHASE_MATCHING')->sum('amount') ?? 0)
                    + (float) $member->downline_monthly_sponsor_income,
            ],
        ]);
    }

    /**
     * Mark a member's monthly snapshot as PAID (irreversible). Sets is_paid=true
     * and paid_at=now() so the member can see the exact date & time the payment
     * was confirmed. There is intentionally no "unmark" — a paid month stays paid.
     */
    public function payMonth(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'memberId' => 'required|integer',
            'yearMonth' => 'required|string|max:7',
        ]);

        $snapshot = MonthlyIncomeSnapshot::where('member_id', $validated['memberId'])
            ->where('year_month', $validated['yearMonth'])
            ->first();

        if (!$snapshot) {
            return response()->json(['message' => 'Income snapshot not found for this month.'], 404);
        }

        $snapshot->is_paid = true;
        $snapshot->paid_at = now();
        $snapshot->save();

        return response()->json([
            'message' => 'Payment confirmed.',
            'is_paid' => true,
            'paid_at' => $snapshot->paid_at->toDateTimeString(),
        ]);
    }
}
