<?php

namespace App\Services;

use App\Models\Category;
use App\Models\PlannedExpense;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Models\UserSetting;
use App\Models\VoiceUsageLog;
use App\Models\Wallet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExportService
{
    /**
     * Stream CSV export of all user transactions.
     */
    public function streamTransactionsCsv(User $user): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="transactions_export_'.date('Y-m-d').'.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($user) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Write CSV headers
            fputcsv($handle, [
                'ID',
                'Date',
                'Type',
                'Amount',
                'Currency',
                'Wallet',
                'Category',
                'Description',
                'Notes',
                'Is Voice Logged',
            ]);

            Transaction::where('user_id', $user->id)
                ->with(['wallet', 'category'])
                ->orderBy('transaction_date', 'desc')
                ->chunk(100, function ($transactions) use ($handle) {
                    foreach ($transactions as $tx) {
                        fputcsv($handle, [
                            $tx->id,
                            $tx->transaction_date ? $tx->transaction_date->toIso8601String() : '',
                            $tx->type,
                            $tx->amount,
                            $tx->currency,
                            $tx->wallet->name ?? '',
                            $tx->category->name ?? '',
                            $tx->description ?? '',
                            $tx->notes ?? '',
                            $tx->is_voice_logged ? 'Yes' : 'No',
                        ]);
                    }
                });

            fclose($handle);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    /**
     * Export complete account JSON backup package.
     */
    public function exportFullJson(User $user): array
    {
        return [
            'exported_at' => now()->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'timezone' => $user->timezone,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'settings' => UserSetting::where('user_id', $user->id)->first(),
            'entitlements' => UserEntitlement::where('user_id', $user->id)->get(),
            'wallets' => Wallet::where('user_id', $user->id)->get(),
            'categories' => Category::where('user_id', $user->id)->get(),
            'transactions' => Transaction::where('user_id', $user->id)->get(),
            'subscriptions' => Subscription::where('user_id', $user->id)->get(),
            'planned_expenses' => PlannedExpense::where('user_id', $user->id)->get(),
            'voice_usage_logs' => VoiceUsageLog::where('user_id', $user->id)->get(),
        ];
    }

    /**
     * Execute permanent GDPR cascade account deletion.
     */
    public function deleteAccount(User $user): void
    {
        // Revoke all authentication tokens
        $user->tokens()->delete();

        // Delete user (FK cascade handles all dependent tables)
        $user->delete();
    }
}
