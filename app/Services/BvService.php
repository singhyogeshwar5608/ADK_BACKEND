<?php

namespace App\Services;

use App\Models\BvLedger;
use App\Models\Member;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BvService
{
    public function __construct(
        private readonly IncomeService $incomeService,
        private readonly MlmSettingsService $settingsService,
        private readonly MLMIncomeService $mlmIncomeService,
    ) {
    }

    public function awardForOrder(Order $order): void
    {
        Log::info('BvService: Starting BV award for order', [
            'order_id' => $order->id,
            'total_bv' => $order->total_bv,
            'bv_awarded_at' => $order->bv_awarded_at,
        ]);

        if ($order->bv_awarded_at || $order->total_bv <= 0) {
            Log::warning('BvService: Skipping BV award', [
                'order_id' => $order->id,
                'already_awarded' => $order->bv_awarded_at ? 'yes' : 'no',
                'total_bv' => $order->total_bv,
            ]);
            return;
        }

        $order->loadMissing('member');
        $purchaser = $order->member;

        if (!$purchaser) {
            Log::warning('BvService: Skipping BV award because order has no member', [
                'order_id' => $order->id,
            ]);
            return;
        }

        Log::info('BvService: Purchaser found', [
            'purchaser_id' => $purchaser->id,
            'purchaser_member_id' => $purchaser->member_id,
            'purchaser_sponsor_id' => $purchaser->sponsor_id,
            'purchaser_is_active' => $purchaser->is_active,
        ]);

        $binarySettings = $this->settingsService->getBinarySettings();

        DB::transaction(function () use ($order, $purchaser, $binarySettings) {
            $amount = (float) $order->total_bv;
            
            // Process complete MLM income for the purchaser
            Log::info('BvService: Processing complete MLM income for purchaser', [
                'order_id' => $order->id,
                'purchaser_id' => $purchaser->id,
                'purchaser_member_id' => $purchaser->member_id,
                'total_bv' => $amount,
            ]);
            
            $isRepurchase = $purchaser->is_repurchase_eligible && $purchaser->first_purchase_at !== null;
            $mlmResults = $this->mlmIncomeService->processPurchaseIncome(
                $purchaser, 
                $amount, 
                'ORDER', 
                $order->id, 
                $isRepurchase
            );
            
            Log::info('BvService: MLM income processed', [
                'order_id' => $order->id,
                'self_income' => $mlmResults['self_income']['amount'] ?? 0,
                'sponsor_income' => $mlmResults['sponsor_income']['amount'] ?? 0,
                'matching_income_count' => count($mlmResults['matching_income'] ?? []),
                'reward_income' => $mlmResults['reward_income']['amount'] ?? 0,
            ]);

            // Update purchaser's own total BV
            $purchaser->bv_total += $amount;
            $purchaser->save();

            $paths = $this->pathsIncludingAncestors($purchaser->placement_path);

            Log::info('BvService: Creating BV ledger entries for ancestors', [
                'order_id' => $order->id,
                'purchaser_placement_path' => $purchaser->placement_path,
                'paths_found' => $paths,
                'amount' => $amount,
            ]);

            $members = Member::query()
                ->whereIn('placement_path', $paths)
                ->lockForUpdate()
                ->get()
                ->keyBy('placement_path');

            foreach ($paths as $path) {
                $target = $members->get($path);
                if (!$target) {
                    continue;
                }

                $direction = $this->directionRelative($path, $purchaser->placement_path);

                BvLedger::create([
                    'member_id' => $target->id,
                    'direction' => $direction,
                    'amount' => $amount,
                    'source_type' => 'ORDER',
                    'source_id' => (string) $order->id,
                    'meta' => [
                        'order_id' => $order->id,
                        'order_total_bv' => $amount,
                        'purchaser_id' => $purchaser->id,
                        'purchaser_member_id' => $purchaser->member_id,
                    ],
                ]);
            }

            $order->bv_awarded_at = now();
            $order->save();

            Log::info('BvService: BV award completed', [
                'order_id' => $order->id,
                'bv_awarded_at' => $order->bv_awarded_at,
            ]);
        });
    }

    private function pathsIncludingAncestors(string $path): array
    {
        $segments = explode('.', $path);
        $paths = [];

        while (!empty($segments)) {
            $paths[] = implode('.', $segments);
            array_pop($segments);
        }

        return $paths;
    }

    private function directionRelative(string $ancestorPath, string $descendantPath): string
    {
        if ($ancestorPath === $descendantPath) {
            return 'SELF';
        }

        if (!str_starts_with($descendantPath, $ancestorPath)) {
            return 'SELF';
        }

        $offset = substr($descendantPath, strlen($ancestorPath));
        $offset = ltrim($offset, '.');
        $first = strtoupper(strtok($offset, '.'));

        return $first === 'L' ? 'LEFT' : 'RIGHT';
    }
}
