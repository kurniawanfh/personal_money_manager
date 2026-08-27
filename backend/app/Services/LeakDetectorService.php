<?php

namespace App\Services;

use App\Models\Category;
use App\Models\PlannedExpense;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSetting;
use Carbon\Carbon;

class LeakDetectorService
{
    /**
     * Analyze potential money leaks for a user across 4 composite rules.
     *
     * @return array{
     *     summary: array{total_leaks_detected: int, potential_monthly_savings: float},
     *     alerts: array<int, array>
     * }
     */
    public function detectLeaks(User $user): array
    {
        $settings = $this->getUserSettings($user);
        $alerts = [];
        $potentialSavings = 0.0;

        // Rule 1: Drip Micro-Spending
        $dripAlert = $this->checkDripMicroSpending($user, $settings);
        if ($dripAlert) {
            $alerts[] = $dripAlert;
            $potentialSavings += $dripAlert['metrics']['total_amount'];
        }

        // Rule 2: Zombie Subscriptions
        $zombieAlerts = $this->checkZombieSubscriptions($user, $settings);
        foreach ($zombieAlerts as $alert) {
            $alerts[] = $alert;
            $potentialSavings += $alert['metrics']['recurring_amount'];
        }

        // Rule 3: Overlapping Subscriptions
        $overlapAlerts = $this->checkOverlappingSubscriptions($user);
        foreach ($overlapAlerts as $alert) {
            $alerts[] = $alert;
            $potentialSavings += $alert['metrics']['combined_amount'];
        }

        // Rule 4: Category Surges
        $surgeAlerts = $this->checkCategorySurges($user, $settings);
        foreach ($surgeAlerts as $alert) {
            $alerts[] = $alert;
        }

        return [
            'summary' => [
                'total_leaks_detected' => count($alerts),
                'potential_monthly_savings' => round($potentialSavings, 2),
            ],
            'alerts' => $alerts,
        ];
    }

    private function getUserSettings(User $user): UserSetting
    {
        return UserSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'drip_max_single_amount' => 25000.00,
                'drip_monthly_threshold' => 500000.00,
                'surge_percentage_threshold' => 150.00,
                'zombie_inactivity_days' => 60,
            ]
        );
    }

    private function checkDripMicroSpending(User $user, UserSetting $settings): ?array
    {
        $maxSingle = (float) $settings->drip_max_single_amount;
        $monthlyThreshold = (float) $settings->drip_monthly_threshold;

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $dripTransactions = Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->where('is_excluded_from_stats', false)
            ->where('amount', '<=', $maxSingle)
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->with('category')
            ->get();

        $totalDrip = (float) $dripTransactions->sum('amount');
        $count = $dripTransactions->count();

        if ($count > 0 && $totalDrip >= $monthlyThreshold) {
            $topCategories = $dripTransactions->groupBy('category.name')
                ->map(fn ($group, $name) => [
                    'category' => $name ?: 'Uncategorized',
                    'amount' => round((float) $group->sum('amount'), 2),
                    'count' => $group->count(),
                ])
                ->sortByDesc('amount')
                ->values()
                ->take(3)
                ->toArray();

            return [
                'rule_key' => 'drip_micro_spending',
                'severity' => 'warning',
                'title' => 'Potential Money Leak: Drip Spending Accumulation',
                'description' => 'You have accumulated Rp '.number_format($totalDrip, 0, ',', '.')." across {$count} micro-transactions (under Rp ".number_format($maxSingle, 0, ',', '.').' each) this month.',
                'recommendation' => 'Review small daily recurring expenses such as snacks, convenience store items, or coffee runs to reduce minor leaks.',
                'metrics' => [
                    'total_amount' => round($totalDrip, 2),
                    'transaction_count' => $count,
                    'threshold' => $monthlyThreshold,
                    'top_categories' => $topCategories,
                ],
            ];
        }

        return null;
    }

    private function checkZombieSubscriptions(User $user, UserSetting $settings): array
    {
        $inactivityDays = (int) $settings->zombie_inactivity_days;
        $cutoffDate = Carbon::now()->subDays($inactivityDays);

        $activeSubscriptions = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        $alerts = [];

        foreach ($activeSubscriptions as $sub) {
            // Check last confirmed planned expense or transaction
            $lastActivity = PlannedExpense::where('subscription_id', $sub->id)
                ->where('status', 'confirmed')
                ->latest('confirmed_at')
                ->first();

            $subAgeInDays = $sub->created_at ? $sub->created_at->diffInDays(Carbon::now()) : 0;

            if ($lastActivity) {
                $daysInactive = $lastActivity->confirmed_at ? $lastActivity->confirmed_at->diffInDays(Carbon::now()) : $inactivityDays + 1;
            } else {
                $daysInactive = $subAgeInDays;
            }

            if ($daysInactive >= $inactivityDays) {
                $alerts[] = [
                    'rule_key' => 'zombie_subscription',
                    'severity' => 'warning',
                    'title' => 'Potential Money Leak: Inactive Zombie Subscription',
                    'description' => "Subscription '{$sub->name}' (Rp ".number_format($sub->estimated_idr_amount, 0, ',', '.')."/cycle) has had no confirmation activity for {$daysInactive} days.",
                    'recommendation' => "If you no longer use {$sub->name}, consider pausing or cancelling it to save money.",
                    'metrics' => [
                        'subscription_id' => $sub->id,
                        'subscription_name' => $sub->name,
                        'recurring_amount' => (float) $sub->estimated_idr_amount,
                        'days_inactive' => $daysInactive,
                    ],
                ];
            }
        }

        return $alerts;
    }

    private function checkOverlappingSubscriptions(User $user): array
    {
        $activeSubscriptions = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('category')
            ->get();

        $alerts = [];

        $byCategory = $activeSubscriptions->groupBy('category_id');

        foreach ($byCategory as $catId => $subs) {
            if ($subs->count() >= 2 && $catId !== null) {
                $catName = $subs->first()->category->name ?? 'Category';
                $subNames = $subs->pluck('name')->join(', ');
                $combinedAmount = (float) $subs->sum('estimated_idr_amount');

                $alerts[] = [
                    'rule_key' => 'overlapping_subscriptions',
                    'severity' => 'info',
                    'title' => "Potential Money Leak: Overlapping Subscriptions in {$catName}",
                    'description' => "You have {$subs->count()} active subscriptions in {$catName} ({$subNames}) totaling Rp ".number_format($combinedAmount, 0, ',', '.').'/cycle.',
                    'recommendation' => 'Review duplicate or overlapping digital services to see if one can be cancelled or shared.',
                    'metrics' => [
                        'category_id' => $catId,
                        'category_name' => $catName,
                        'subscription_ids' => $subs->pluck('id')->toArray(),
                        'combined_amount' => round($combinedAmount, 2),
                    ],
                ];
            }
        }

        return $alerts;
    }

    private function checkCategorySurges(User $user, UserSetting $settings): array
    {
        $surgeThreshold = (float) $settings->surge_percentage_threshold; // e.g. 150%

        $currentStart = Carbon::now()->startOfMonth();
        $currentEnd = Carbon::now()->endOfMonth();

        $prevStart = Carbon::now()->subMonth()->startOfMonth();
        $prevEnd = Carbon::now()->subMonth()->endOfMonth();

        $currentSpending = Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->where('is_excluded_from_stats', false)
            ->whereNotNull('category_id')
            ->whereBetween('transaction_date', [$currentStart, $currentEnd])
            ->groupBy('category_id')
            ->selectRaw('category_id, SUM(amount) as total')
            ->pluck('total', 'category_id');

        $prevSpending = Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->where('is_excluded_from_stats', false)
            ->whereNotNull('category_id')
            ->whereBetween('transaction_date', [$prevStart, $prevEnd])
            ->groupBy('category_id')
            ->selectRaw('category_id, SUM(amount) as total')
            ->pluck('total', 'category_id');

        $alerts = [];

        foreach ($currentSpending as $catId => $currAmount) {
            $prevAmount = (float) ($prevSpending[$catId] ?? 0);

            if ($prevAmount > 0) {
                $growthPercentage = ($currAmount / $prevAmount) * 100;

                if ($growthPercentage >= $surgeThreshold) {
                    $category = Category::find($catId);
                    $catName = $category?->name ?? 'Category';

                    $alerts[] = [
                        'rule_key' => 'category_surge',
                        'severity' => 'warning',
                        'title' => "Potential Money Leak: Spending Surge in {$catName}",
                        'description' => "Spending in '{$catName}' is at ".round($growthPercentage, 1).'% compared to last month (Rp '.number_format($currAmount, 0, ',', '.').' vs Rp '.number_format($prevAmount, 0, ',', '.').').',
                        'recommendation' => "Analyze recent expenses in {$catName} to verify unexpected increases.",
                        'metrics' => [
                            'category_id' => $catId,
                            'category_name' => $catName,
                            'current_amount' => round((float) $currAmount, 2),
                            'previous_amount' => round($prevAmount, 2),
                            'growth_percentage' => round($growthPercentage, 1),
                        ],
                    ];
                }
            }
        }

        return $alerts;
    }
}
