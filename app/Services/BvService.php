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
    ) {
    }

    public function awardForOrder(Order $order): void
    {
        if ($order->bv_awarded_at || $order->total_bv <= 0) {
            return;
        }

        $order->loadMissing('member');
        $purchaser = $order->member;

        if (!$purchaser) {
            Log::warning('Skipping BV award because order has no member', [
                'order_id' => $order->id,
            ]);
            return;
        }

        $binarySettings = $this->settingsService->getBinarySettings();

        DB::transaction(function () use ($order, $purchaser, $binarySettings) {
            $amount = (float) $order->total_bv;
            $paths = $this->pathsIncludingAncestors($purchaser->placement_path);

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

                $target->bv_total = (float) $target->bv_total + $amount;

                if ($direction === 'LEFT') {
                    $target->bv_left_leg = (float) $target->bv_left_leg + $amount;
                    $target->bv_carry_forward_left = (float) $target->bv_carry_forward_left + $amount;
                } elseif ($direction === 'RIGHT') {
                    $target->bv_right_leg = (float) $target->bv_right_leg + $amount;
                    $target->bv_carry_forward_right = (float) $target->bv_carry_forward_right + $amount;
                }

                $target->save();

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

                if ($direction !== 'SELF') {
                    $this->incomeService->applyBinaryIncome($target, $binarySettings);
                }
            }

            $order->bv_awarded_at = now();
            $order->save();
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
