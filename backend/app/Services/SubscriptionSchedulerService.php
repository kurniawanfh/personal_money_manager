<?php

namespace App\Services;

use App\Models\PlannedExpense;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionSchedulerService
{
    /**
     * Process due subscriptions and create pending planned expenses.
     *
     * @return array{processed: int, created: int}
     */
    public function processBilling(?string $targetDate = null): array
    {
        $date = $targetDate ? Carbon::parse($targetDate) : Carbon::today();
        $dateString = $date->toDateString();

        $dueSubscriptions = Subscription::where('status', 'active')
            ->whereDate('next_billing_date', '<=', $dateString)
            ->get();

        $processedCount = 0;
        $createdCount = 0;

        foreach ($dueSubscriptions as $sub) {
            $processedCount++;

            $billingCycleKey = $this->calculateBillingCycleKey($sub);

            DB::transaction(function () use ($sub, $billingCycleKey, &$createdCount) {
                // Check if planned expense already exists for this cycle (idempotency)
                $existing = PlannedExpense::where('subscription_id', $sub->id)
                    ->where('billing_cycle_key', $billingCycleKey)
                    ->first();

                if (! $existing) {
                    PlannedExpense::create([
                        'id' => (string) Str::uuid(),
                        'user_id' => $sub->user_id,
                        'subscription_id' => $sub->id,
                        'wallet_id' => $sub->wallet_id,
                        'category_id' => $sub->category_id,
                        'estimated_idr_amount' => $sub->estimated_idr_amount,
                        'actual_idr_amount' => null,
                        'due_date' => Carbon::parse($sub->next_billing_date)->toDateString(),
                        'billing_cycle_key' => $billingCycleKey,
                        'status' => 'pending',
                        'server_revision' => 1,
                    ]);
                    $createdCount++;
                }

                // Advance next billing date
                $nextDate = $this->calculateNextBillingDate($sub);
                $sub->update([
                    'next_billing_date' => $nextDate->toDateString(),
                    'server_revision' => $sub->server_revision + 1,
                ]);
            });
        }

        return [
            'processed' => $processedCount,
            'created' => $createdCount,
        ];
    }

    /**
     * Calculate unique billing cycle key based on cycle type.
     */
    public function calculateBillingCycleKey(Subscription $subscription): string
    {
        $dueDate = Carbon::parse($subscription->next_billing_date);

        return match ($subscription->billing_cycle) {
            'weekly' => $dueDate->format('o-\WW'), // ISO-8601 week e.g. 2026-W35
            'yearly' => $dueDate->format('Y'),
            default => $dueDate->format('Y-m'),
        };
    }

    /**
     * Advance next billing date based on cycle and billing day.
     */
    public function calculateNextBillingDate(Subscription $subscription): Carbon
    {
        $currentDate = Carbon::parse($subscription->next_billing_date);

        return match ($subscription->billing_cycle) {
            'weekly' => $currentDate->copy()->addWeek(),
            'yearly' => $currentDate->copy()->addYear(),
            default => $this->calculateNextMonthlyDate($currentDate, $subscription->billing_day),
        };
    }

    /**
     * Safely calculate next monthly date handling month length differences (e.g. day 31).
     */
    private function calculateNextMonthlyDate(Carbon $currentDate, int $billingDay): Carbon
    {
        $nextMonth = $currentDate->copy()->addMonthNoOverflow();
        $daysInNextMonth = $nextMonth->daysInMonth;
        $targetDay = min($billingDay, $daysInNextMonth);

        return $nextMonth->day($targetDay);
    }
}
