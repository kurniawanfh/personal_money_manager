<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\NotificationDispatch;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * Register or update device push token for a user.
     */
    public function registerDeviceToken(User $user, string $token, string $deviceType = 'android'): DeviceToken
    {
        return DeviceToken::updateOrCreate(
            ['token' => $token],
            [
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'device_type' => $deviceType,
            ]
        );
    }

    /**
     * Remove device token on logout.
     */
    public function removeDeviceToken(User $user, string $token): void
    {
        DeviceToken::where('user_id', $user->id)->where('token', $token)->delete();
    }

    /**
     * Send idempotent subscription renewal reminders (H-3 and H-1).
     *
     * @return array{dispatched: int, skipped: int}
     */
    public function dispatchSubscriptionReminders(?string $targetDate = null): array
    {
        $date = ($targetDate ? Carbon::parse($targetDate) : Carbon::today())->startOfDay();

        $activeSubscriptions = Subscription::where('status', 'active')->with(['user', 'category'])->get();

        $dispatchedCount = 0;
        $skippedCount = 0;

        foreach ($activeSubscriptions as $sub) {
            $nextBilling = Carbon::parse($sub->next_billing_date)->startOfDay();
            $daysDiff = (int) $date->diffInDays($nextBilling, false); // Days until billing

            $cycleKey = Carbon::parse($sub->next_billing_date)->format('Y-m');

            // H-3 Reminder
            if ($daysDiff === 3 && $sub->remind_h3) {
                $idempotencyKey = "sub_{$sub->id}_{$cycleKey}_h3";
                if ($this->sendReminderNotification($sub, 3, $idempotencyKey)) {
                    $dispatchedCount++;
                } else {
                    $skippedCount++;
                }
            }

            // H-1 Reminder
            if ($daysDiff === 1 && $sub->remind_h1) {
                $idempotencyKey = "sub_{$sub->id}_{$cycleKey}_h1";
                if ($this->sendReminderNotification($sub, 1, $idempotencyKey)) {
                    $dispatchedCount++;
                } else {
                    $skippedCount++;
                }
            }
        }

        return [
            'dispatched' => $dispatchedCount,
            'skipped' => $skippedCount,
        ];
    }

    private function sendReminderNotification(Subscription $sub, int $daysBefore, string $idempotencyKey): bool
    {
        // Check if already dispatched for this cycle and interval
        $alreadySent = NotificationDispatch::where('idempotency_key', $idempotencyKey)->exists();
        if ($alreadySent) {
            return false;
        }

        // Record dispatch idempotency key
        NotificationDispatch::create([
            'id' => (string) Str::uuid(),
            'user_id' => $sub->user_id,
            'idempotency_key' => $idempotencyKey,
            'channel' => 'fcm',
            'dispatched_at' => Carbon::now(),
        ]);

        $title = "Upcoming Subscription Renewal: {$sub->name}";
        $body = "Your {$sub->name} subscription (Rp ".number_format($sub->estimated_idr_amount, 0, ',', '.').") renews in {$daysBefore} ".($daysBefore === 1 ? 'day' : 'days').'.';

        Log::info("Notification dispatched to User {$sub->user_id}: [{$title}] {$body}");

        return true;
    }
}
