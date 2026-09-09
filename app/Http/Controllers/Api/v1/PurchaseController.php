<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Order;
use App\Models\Product;
use App\Services\MLMIncomeService;
use App\Services\MlmSettingsService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly MLMIncomeService $mlmIncomeService,
        private readonly WhatsAppService $whatsAppService,
        private readonly MlmSettingsService $settingsService
    ) {}

    /**
     * Process a product purchase with complete MLM income distribution
     */
    public function processPurchase(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $result = DB::transaction(function () use ($validated) {
                // Get member and product
                $member = Member::lockForUpdate()->findOrFail($validated['member_id']);
                $product = Product::findOrFail($validated['product_id']);

                if (! $product->is_active) {
                    throw new \Exception('This product is not available for purchase');
                }

                // Check stock
                if ($product->stock < $validated['quantity']) {
                    throw new \Exception('Insufficient stock available');
                }

                // Calculate totals
                $quantity = $validated['quantity'];
                $totalPrice = $product->total_price * $quantity;
                $totalBV = $product->bv * $quantity;

                // Create order
                $order = Order::create([
                    'member_id' => $member->id,
                    'member_snapshot' => [
                        'memberId' => $member->member_id,
                        'fullName' => $member->full_name,
                        'email' => $member->email,
                        'phone' => $member->phone,
                        'serialNo' => $member->serial_no,
                    ],
                    'total_amount' => $totalPrice,
                    'total_bv' => $totalBV,
                    'status' => 'PENDING',
                    'payment_status' => 'PENDING',
                    'items' => [
                        [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'quantity' => $quantity,
                            'price' => $product->total_price,
                            'bv' => $product->bv,
                            'total_price' => $totalPrice,
                            'total_bv' => $totalBV,
                            'hsn_code' => $product->hsn_code,
                        ],
                    ],
                ]);

                // Update product stock
                $product->decrement('stock', $quantity);

                // Determine if this is a repurchase
                $isRepurchase = $member->is_repurchase_eligible && $member->first_purchase_at !== null;

                // Process MLM income distribution
                $incomeResults = $this->mlmIncomeService->processPurchaseIncome(
                    $member,
                    $totalBV,
                    'ORDER',
                    $order->id,
                    $isRepurchase
                );

                // Track self-purchase BV and total BV for the purchaser
                $member->bv_total += $totalBV;
                $member->self_purchase_bv += $totalBV;
                $member->save();

                // Mark order as BV awarded
                $order->bv_awarded_at = now();
                $order->save();

                return [
                    'success' => true,
                    'order' => $order,
                    'income_distribution' => $incomeResults,
                    'is_repurchase' => $isRepurchase,
                ];
            });

            $this->whatsAppService->sendOrderNotification($result['order']);

            $whatsAppLink = $this->whatsAppService->getOrderWhatsAppLink($result['order']);

            $result['whatsapp_link'] = $whatsAppLink;

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Purchase processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get income summary for a member
     */
    public function getIncomeSummary(Request $request, string $memberId): JsonResponse
    {
        $member = Member::where('member_id', $memberId)->firstOrFail();

        // Weekly cap lives in mlm_settings (admin-editable), not hardcoded.
        $weeklyCap = (int) $this->settingsService->getSetting('weekly_capping', 50000);

        $summary = [
            'member_id' => $member->id,
            'member_name' => $member->full_name,
            'wallet_balance' => (float) $member->wallet_balance,
            'wallet_total_earned' => (float) $member->wallet_total_earned,
            'weekly_income' => (float) $member->weekly_income,
            'weekly_cap_remaining' => max(0, $weeklyCap - (float) $member->weekly_income),
            'weekly_cap' => $weeklyCap,
            'is_active' => (bool) $member->is_active,
            'is_repurchase_eligible' => (bool) $member->is_repurchase_eligible,
            'reward_eligible' => (bool) $member->reward_eligible,
            'direct_referrals_count' => $member->direct_referrals_count,
            'bv_summary' => [
                'total_bv' => (float) $member->bv_total,
                'left_leg_bv' => (float) $member->bv_left_leg,
                'right_leg_bv' => (float) $member->bv_right_leg,
                'carry_forward_left' => (float) $member->bv_carry_forward_left,
                'carry_forward_right' => (float) $member->bv_carry_forward_right,
                'total_matched_bv' => (float) $member->total_matched_bv,
                'first_match_done' => (bool) $member->first_match_done,
            ],
        ];

        return response()->json($summary);
    }

    /**
     * Get income transactions for a member
     */
    public function getIncomeTransactions(Request $request, string $memberId): JsonResponse
    {
        $member = Member::where('member_id', $memberId)->firstOrFail();

        $transactions = $member->incomeTransactions()
            ->with('fromMember:id,member_id,full_name')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json($transactions);
    }

    /**
     * Get matching history for a member
     */
    public function getMatchingHistory(Request $request, string $memberId): JsonResponse
    {
        $member = Member::where('member_id', $memberId)->firstOrFail();

        $history = $member->matchingHistory()
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json($history);
    }

    /**
     * Get wallet transactions for a member
     */
    public function getWalletTransactions(Request $request, string $memberId): JsonResponse
    {
        $member = Member::where('member_id', $memberId)->firstOrFail();

        $transactions = $member->walletTransactions()
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json($transactions);
    }

    /**
     * Get BV transactions for a member
     */
    public function getBVTransactions(Request $request, string $memberId): JsonResponse
    {
        $member = Member::where('member_id', $memberId)->firstOrFail();

        $transactions = $member->bvLedger()
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json($transactions);
    }

    /**
     * Manual income adjustment (admin only)
     */
    public function adjustIncome(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'amount' => ['required', 'numeric'],
            'type' => ['required', 'in:CREDIT,DEBIT'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $result = DB::transaction(function () use ($validated) {
                $member = Member::lockForUpdate()->findOrFail($validated['member_id']);
                $amount = abs($validated['amount']);

                if ($validated['type'] === 'DEBIT' && $member->wallet_balance < $amount) {
                    throw new \Exception('Insufficient wallet balance');
                }

                // Update wallet
                if ($validated['type'] === 'CREDIT') {
                    $member->wallet_balance += $amount;
                    $member->wallet_total_earned += $amount;
                } else {
                    $member->wallet_balance -= $amount;
                }

                $member->save();

                // Create wallet transaction
                $transaction = $member->walletTransactions()->create([
                    'type' => $validated['type'],
                    'amount' => $amount,
                    'balance_after' => $member->wallet_balance,
                    'reference' => 'ADJ-' . now()->timestamp . '-' . $member->id,
                    'context' => 'MANUAL_ADJUSTMENT',
                    'meta' => [
                        'reason' => $validated['reason'],
                        'adjusted_by' => auth()->id(),
                    ],
                ]);

                return [
                    'success' => true,
                    'member' => $member,
                    'transaction' => $transaction,
                ];
            });

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get income statistics
     */
    public function getIncomeStatistics(Request $request, string $memberId): JsonResponse
    {
        $member = Member::where('member_id', $memberId)->firstOrFail();

        // Income cycle start comes from the admin-managed `income_cycle_start_day`
        // setting (see MlmSettingsService::getIncomeCycleStart), replacing the old
        // temporary subMonth() hack so each month shows only its own income.
        $cycleStart = $this->settingsService->getIncomeCycleStart();

        $incomeByType = $member->incomeTransactions()
            ->where('created_at', '>=', $cycleStart)
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $sumOf = function (array $types) use ($incomeByType): float {
            $total = 0.0;
            foreach ($types as $type) {
                $total += (float) ($incomeByType[$type] ?? 0);
            }

            return $total;
        };

        // Category totals pulled straight from the income_transactions table.
        // Mirrors the seven income cards shown on the Flutter Profile screen.
        $selfPurchase = $sumOf(['SELF']);
        $selfRepurchase = $sumOf(['REPURCHASE_SELF']);
        $sponsor = $sumOf(['SPONSOR']);
        $sponsorAwardKit = $sumOf(['SPONSOR_AWARD']);
        $matching = $sumOf(['MATCHING']);
        $repurchaseMatching = $sumOf(['REPURCHASE_MATCHING']);
        $reward = $sumOf(['REWARD', 'REPURCHASE_REWARD']);
        $downlineMonthlySponsor = (float) $member->downline_monthly_sponsor_income;

        $total = $selfPurchase + $selfRepurchase + $sponsor + $sponsorAwardKit
            + $matching + $repurchaseMatching + $reward + $downlineMonthlySponsor;

        // Weekly cap lives in mlm_settings (admin-editable), not hardcoded.
        $weeklyCap = (int) $this->settingsService->getSetting('weekly_capping', 50000);

        $stats = [
            'total_income' => [
                'self' => $selfPurchase + $selfRepurchase,
                'sponsor' => $sponsor,
                'matching' => $matching + $repurchaseMatching,
                'reward' => $reward,
            ],
            'income_breakdown' => [
                'self_purchase' => $selfPurchase,
                'sponsor' => $sponsor,
                'matching' => $matching,
                'self_repurchase' => $selfRepurchase,
                'repurchase_matching' => $repurchaseMatching,
                'sponsor_award_kit' => $sponsorAwardKit,
                'downline_monthly_sponsor' => $downlineMonthlySponsor,
                'reward' => $reward,
            ],
            'total' => $total,
            'this_week' => [
                'total' => (float) $member->weekly_income,
                'cap_remaining' => max(0, $weeklyCap - (float) $member->weekly_income),
                'cap' => $weeklyCap,
            ],
            'this_month' => [
                'total' => $member->incomeTransactions()
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->sum('amount'),
            ],
            'total_transactions' => $member->incomeTransactions()->count(),
            'total_matches' => $member->matchingHistory()->count(),
        ];

        return response()->json($stats);
    }
}
