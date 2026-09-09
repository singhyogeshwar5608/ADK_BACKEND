<?php

namespace App\Console\Commands;

use App\Models\IncomeTransaction;
use App\Models\Member;
use App\Services\MlmSettingsService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PreviewMonthlyIncome extends Command
{
    public function __construct(private readonly MlmSettingsService $settingsService)
    {
        parent::__construct();
    }

    protected $signature = 'mlm:preview-monthly-income
                            {--member= : Filter by member_id (optional)}
                            {--month= : Month in Y-m format (defaults to current month)}';

    protected $description = 'Preview monthly income breakdown before the 30th snapshot (read-only, no data is modified)';

    private const EARNING_TYPES = [
        'SELF', 'REPURCHASE_SELF',
        'SPONSOR', 'SPONSOR_AWARD',
        'MATCHING', 'REPURCHASE_MATCHING',
        'REWARD', 'REPURCHASE_REWARD',
    ];

    public function handle(): int
    {
        $month = $this->option('month') ?: Carbon::now()->subMonth()->format('Y-m');
        $memberFilter = $this->option('member');

        // Same cycle boundaries as the actual monthly process, so the preview
        // always matches what the snapshot/deduction will compute.
        $startDate = $this->settingsService->getIncomeCycleStartForMonth($month);
        $endDate = $this->settingsService->getIncomeCycleEndForMonth($month);

        $this->info("=== Monthly Income Preview: {$month} ===");
        $this->line("Period: {$startDate->toDateString()} to {$endDate->toDateString()}");
        $this->line("Mode: READ-ONLY (no deductions, no snapshots)");
        $this->newLine();

        $memberIncomes = IncomeTransaction::selectRaw('member_id, SUM(amount) as total_income')
            ->whereIn('type', self::EARNING_TYPES)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('member_id');

        if ($memberFilter) {
            $memberIncomes->whereHas('member', fn($q) => $q->where('member_id', $memberFilter));
        }

        $memberIncomes = $memberIncomes->get();

        if ($memberIncomes->isEmpty()) {
            $this->warn("No income found for {$month}.");
            return 0;
        }

        $this->info("Showing income preview for {$memberIncomes->count()} member(s):");
        $this->newLine();

        $rows = [];

        foreach ($memberIncomes as $row) {
            $member = Member::find($row->member_id);
            if (!$member) {
                continue;
            }

            $direct = (float) $member->incomeLedger()
                ->where('type', 'direct')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('amount');

            $selfPurchase = (float) $member->incomeTransactions()
                ->where('type', 'SELF')->whereBetween('created_at', [$startDate, $endDate])->sum('amount');

            $selfRepurchase = (float) $member->incomeTransactions()
                ->where('type', 'REPURCHASE_SELF')->whereBetween('created_at', [$startDate, $endDate])->sum('amount');

            $sponsor = (float) $member->incomeTransactions()
                ->where('type', 'SPONSOR')->whereBetween('created_at', [$startDate, $endDate])->sum('amount');

            $sponsorAwardKit = (float) $member->incomeTransactions()
                ->where('type', 'SPONSOR_AWARD')->whereBetween('created_at', [$startDate, $endDate])->sum('amount');

            $matching = (float) $member->incomeTransactions()
                ->where('type', 'MATCHING')->whereBetween('created_at', [$startDate, $endDate])->sum('amount');

            $repurchaseMatching = (float) $member->incomeTransactions()
                ->where('type', 'REPURCHASE_MATCHING')->whereBetween('created_at', [$startDate, $endDate])->sum('amount');

            $walletBalance = (float) $member->wallet_balance;
            $totalIncome = (float) $row->total_income;
            $tierAmount = round($totalIncome * 0.05, 2);

            $total = $direct + $selfPurchase + $selfRepurchase + $sponsor
                + $sponsorAwardKit + $matching + $repurchaseMatching;

            $hasSnapshot = \App\Models\MonthlyIncomeSnapshot::where('member_id', $member->id)
                ->where('year_month', $month)->exists();

            $rows[] = [
                $member->member_id,
                $member->full_name,
                number_format($direct, 2),
                number_format($selfPurchase, 2),
                number_format($sponsor, 2),
                number_format($matching, 2),
                number_format($totalIncome, 2),
                number_format($tierAmount, 2),
                number_format($walletBalance, 2),
                $hasSnapshot ? 'Yes' : 'No',
            ];
        }

        $this->table(
            ['Member ID', 'Name', 'Direct', 'Self Pur.', 'Sponsor', 'Matching', 'Gross Total', 'Tier(5%)', 'Wallet', 'Snapshot?'],
            $rows
        );

        $this->newLine();
        $this->info("To process deductions & create snapshots, run:");
        $this->info("  php artisan mlm:process-monthly-deductions --month={$month}");

        return 0;
    }
}
