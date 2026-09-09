<?php

namespace App\Console\Commands;

use App\Models\IncomeTransaction;
use App\Models\Member;
use App\Models\MonthlyIncomeSnapshot;
use App\Models\WalletTransaction;
use App\Services\MlmSettingsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessMonthlyDeductions extends Command
{
    public function __construct(private readonly MlmSettingsService $settingsService)
    {
        parent::__construct();
    }

    protected $signature = 'mlm:process-monthly-deductions {--month= : The month to process (format: Y-m), defaults to previous month} {--force : Process even if the month was already processed}';

    protected $description = 'Calculate and deduct 5% Monthly Tier (to direct sponsor) from each member\'s total monthly income';

    private const EARNING_TYPES = [
        'SELF', 'REPURCHASE_SELF',
        'SPONSOR', 'SPONSOR_AWARD',
        'MATCHING', 'REPURCHASE_MATCHING',
        'REWARD', 'REPURCHASE_REWARD',
    ];

    private const SELF_PURCHASE_BV_THRESHOLD = 5000;

    public function handle(): int
    {
        $month = $this->option('month') ?: Carbon::now()->subMonth()->format('Y-m');
        // Income aggregation runs on the same cycle boundaries as the tier date
        // and the snapshot window (e.g. with income_cycle_start_day = 5 the
        // September cycle is Sep 5 -> Oct 4), so a member's income, the 5% tier
        // and the snapshot all live in the same cycle.
        $startDate = $this->settingsService->getIncomeCycleStartForMonth($month);
        $endDate = $this->settingsService->getIncomeCycleEndForMonth($month);

        $this->info("Processing monthly deductions for {$month} ({$startDate->toDateString()} to {$endDate->toDateString()})...");

        // Idempotency guard: a month must never be processed twice, otherwise the
        // 5% tier would be deducted again (double deduction). Safe to re-run the
        // command — it simply skips months that are already done.
        $alreadyProcessed = MonthlyIncomeSnapshot::where('year_month', $month)->exists()
            || IncomeTransaction::where('type', 'MONTHLY_TIER')
                ->where('meta->month', $month)
                ->exists();

        if ($alreadyProcessed && ! $this->option('force')) {
            $this->warn("Month {$month} has already been processed — skipping to avoid double deductions. Use --force to re-process anyway.");
            return 0;
        }

        $memberIncomes = IncomeTransaction::selectRaw('member_id, SUM(amount) as total_income')
            ->whereIn('type', self::EARNING_TYPES)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('member_id')
            ->get();

        if ($memberIncomes->isEmpty()) {
            $this->info("No income found for {$month}.");
        } else {
            $this->info("Found {$memberIncomes->count()} members with income in {$month}.");
        }

        $processed = 0;
        $skipped = 0;

        // --- FIRST PASS: Deductions + reset (no snapshots yet) ---
        foreach ($memberIncomes as $row) {
            $result = DB::transaction(function () use ($row, $month) {
                $member = Member::lockForUpdate()->find($row->member_id);
                if (!$member || (float) $row->total_income <= 0) {
                    return false;
                }

                // Skip admin — company owner, no deductions (just reset)
                if ($member->role === 'ADMIN') {
                    $member->income_reset_at = now();
                    $member->save();
                    $this->line("  Admin {$member->member_id}: Skipped (company owner, no deductions)");
                    return $member->id;
                }

                $totalIncome = (float) $row->total_income;

                // --- Monthly Tier: always 5% regardless of income ---
                $tierAmount = round($totalIncome * 0.05, 2);

                // Resolve direct sponsor for tier
                $tierSponsor = null;
                if ($member->referred_by) {
                    $tierSponsor = Member::where('member_id', $member->referred_by)->lockForUpdate()->first();
                }
                if (!$tierSponsor && $member->sponsor_id) {
                    $tierSponsor = Member::lockForUpdate()->find($member->sponsor_id);
                }

                $actualTierAmount = $tierSponsor ? $tierAmount : 0.0;
                $actualTotalDeduction = $actualTierAmount;

                // Check sufficient balance (only for actual deductions)
                $walletBalance = (float) $member->wallet_balance;
                if ($actualTotalDeduction > 0 && $walletBalance < $actualTotalDeduction) {
                    Log::warning("ProcessMonthlyDeductions: Insufficient balance for member #{$member->id}", [
                        'member_id' => $member->id,
                        'member_code' => $member->member_id,
                        'wallet_balance' => $walletBalance,
                        'required_deduction' => $actualTotalDeduction,
                        'total_income' => $totalIncome,
                    ]);
                    $this->warn("Member {$member->member_id}: Insufficient balance ({$walletBalance}) for deductions ({$actualTotalDeduction}). Skipped.");
                    return false;
                }

                // Deduct from member
                if ($actualTotalDeduction > 0) {
                    $member->wallet_balance -= $actualTotalDeduction;
                    $member->save();
                }

                // --- Credit 5% Monthly Tier to direct sponsor (always, if sponsor is income eligible) ---
                if ($tierSponsor && $tierAmount > 0) {
                    $sponsorSelfBv = (float) ($tierSponsor->self_purchase_bv ?? 0);
                    $isSponsorEligible = $tierSponsor->role === 'ADMIN' || $sponsorSelfBv >= self::SELF_PURCHASE_BV_THRESHOLD;
                    if (!$isSponsorEligible) {
                        $this->line("  Tier: Sponsor {$tierSponsor->member_id} not income eligible (self_purchase_bv {$sponsorSelfBv} < " . self::SELF_PURCHASE_BV_THRESHOLD . "), skipped.");
                    } else {
                        $tierSponsor->wallet_balance += $tierAmount;
                        $tierSponsor->wallet_total_earned += $tierAmount;
                        $tierSponsor->save();

                        // The tier belongs to the processed cycle, not the day the
                        // command runs. The date follows the admin-managed
                        // `income_cycle_start_day` setting (soft/configured): with
                        // day = 1 the tier is back-dated to the calendar month end
                        // (Sep 30), with day = 5 it is back-dated to the cycle end
                        // (Oct 4 for the Sep 5 -> Oct 4 cycle). Back-dating lets the
                        // monthly snapshot (which filters by created_at) pick it up,
                        // so each cycle's tier is counted against its own cycle.
                        $tierDate = $this->settingsService->getIncomeCycleEndForMonth($month);

                        // Use raw query-builder inserts (not Eloquent create) so the
                        // back-dated created_at/updated_at are stored exactly as given.
                        // Eloquent's create() would silently override these timestamps
                        // with now(), which is why the tier was never picked up by the
                        // monthly snapshot. Raw inserts also keep this permanent across
                        // future months: each month's tier gets that month's end date,
                        // so every month's snapshot counts its own tier correctly.
                        DB::table('income_transactions')->insert([
                            'member_id' => $tierSponsor->id,
                            'type' => 'MONTHLY_TIER',
                            'amount' => $tierAmount,
                            'bv' => 0,
                            'source_type' => 'MONTHLY_DEDUCTION',
                            'source_id' => $member->id,
                            'from_member_id' => $member->id,
                            'description' => "5% monthly tier from {$member->member_id} income ({$month})",
                            'meta' => json_encode([
                                'source_member_id' => $member->id,
                                'source_member_name' => $member->full_name,
                                'source_member_code' => $member->member_id,
                                'month' => $month,
                                'total_monthly_income' => $totalIncome,
                                'tier_percentage' => 5,
                            ]),
                            'is_capped' => false,
                            'created_at' => $tierDate,
                            'updated_at' => $tierDate,
                        ]);

                        DB::table('wallet_transactions')->insert([
                            'member_id' => $tierSponsor->id,
                            'type' => 'CREDIT',
                            'amount' => $tierAmount,
                            'balance_after' => $tierSponsor->wallet_balance,
                            'reference' => 'MTIER-' . now()->timestamp . '-' . $tierSponsor->id,
                            'context' => 'MONTHLY_TIER',
                            'meta' => json_encode(['from_member_id' => $member->id, 'month' => $month]),
                            'created_at' => $tierDate,
                            'updated_at' => $tierDate,
                        ]);

                        DB::table('wallet_transactions')->insert([
                            'member_id' => $member->id,
                            'type' => 'DEBIT',
                            'amount' => $tierAmount,
                            'balance_after' => $member->wallet_balance,
                            'reference' => 'MTIER-DEBIT-' . now()->timestamp . '-' . $member->id,
                            'context' => 'MONTHLY_TIER_DEDUCTION',
                            'meta' => json_encode(['month' => $month, 'deduction_type' => 'TIER', 'percentage' => 5]),
                            'created_at' => $tierDate,
                            'updated_at' => $tierDate,
                        ]);

                        $this->line("  Tier: {$tierAmount} deducted from {$member->member_id} → {$tierSponsor->member_id}");
                    }
                } else {
                    $this->line("  Tier: No sponsor found for {$member->member_id}, skipped.");
                }

                // --- Reset income ---
                $member->income_reset_at = now();
                $member->save();

                return $member->id;
            });

            if ($result && is_int($result)) {
                $processed++;
            } else {
                $skipped++;
            }
        }

        // --- SECOND PASS: Save snapshots for ALL members ---
        $this->newLine();
        $this->info("Saving income snapshots for {$month} (all members)...");

        $allMembers = Member::all();
        $snapshotsSaved = 0;
        foreach ($allMembers as $member) {
            $this->saveIncomeSnapshot($member, $month);
            $snapshotsSaved++;
        }

        $this->newLine();
        $this->info("Monthly deductions completed for {$month}.");
        $this->info("  Deductions processed: {$processed}");
        $this->info("  Deductions skipped:   {$skipped}");
        $this->info("  Snapshots saved:      {$snapshotsSaved}");

        return 0;
    }

    private function saveIncomeSnapshot(Member $member, string $month): void
    {
        // Same cycle boundaries as the income aggregation and tier date, so a
        // member's snapshot, income and tier all belong to the same cycle.
        $start = $this->settingsService->getIncomeCycleStartForMonth($month);
        $end = $this->settingsService->getIncomeCycleEndForMonth($month);

        $direct = (float) $member->incomeLedger()->where('type', 'direct')
            ->whereBetween('created_at', [$start, $end])->sum('amount');

        $selfPurchase = (float) $member->incomeTransactions()
            ->where('type', 'SELF')->whereBetween('created_at', [$start, $end])->sum('amount');

        $selfRepurchase = (float) $member->incomeTransactions()
            ->where('type', 'REPURCHASE_SELF')->whereBetween('created_at', [$start, $end])->sum('amount');

        $sponsor = (float) $member->incomeTransactions()
            ->where('type', 'SPONSOR')->whereBetween('created_at', [$start, $end])->sum('amount');

        $sponsorAwardKit = (float) $member->incomeTransactions()
            ->where('type', 'SPONSOR_AWARD')->whereBetween('created_at', [$start, $end])->sum('amount');

        $matching = (float) $member->incomeTransactions()
            ->where('type', 'MATCHING')->whereBetween('created_at', [$start, $end])->sum('amount');

        $repurchaseMatching = (float) $member->incomeTransactions()
            ->where('type', 'REPURCHASE_MATCHING')->whereBetween('created_at', [$start, $end])->sum('amount');

        $downlineMonthlySponsor = (float) IncomeTransaction::where('member_id', $member->id)
            ->where('type', 'MONTHLY_TIER')->whereBetween('created_at', [$start, $end])->sum('amount');

        $total = $direct + $selfPurchase + $selfRepurchase + $sponsor
            + $sponsorAwardKit + $matching + $repurchaseMatching
            + $downlineMonthlySponsor;

        MonthlyIncomeSnapshot::updateOrCreate(
            ['member_id' => $member->id, 'year_month' => $month],
            [
                'direct' => $direct,
                'matching' => $matching,
                'self_purchase' => $selfPurchase,
                'self_repurchase' => $selfRepurchase,
                'sponsor' => $sponsor,
                'sponsor_award_kit' => $sponsorAwardKit,
                'repurchase_matching' => $repurchaseMatching,
                'downline_monthly_sponsor' => $downlineMonthlySponsor,
                'tds' => 0,
                'total' => $total,
            ]
        );

        $this->line("  Snapshot saved for {$member->member_id} ({$month}): total {$total}");
    }
}
