<?php

namespace App\Services;

use App\Exceptions\VoiceQuotaExceededException;
use App\Models\User;
use App\Models\VoiceUsageLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VoiceQuotaService
{
    public const FREE_MONTHLY_LIMIT = 10;

    public function __construct(
        protected VoiceParserService $parserService,
        protected EntitlementService $entitlementService
    ) {}

    /**
     * Get current quota status for user.
     *
     * @return array{is_premium: bool, limit: int, used: int, remaining: int, reset_date: string}
     */
    public function getQuota(User $user): array
    {
        $isPremium = $this->entitlementService->isPremium($user);
        $resetDate = Carbon::now()->addMonthNoOverflow()->startOfMonth()->toIso8601String();

        if ($isPremium) {
            return [
                'is_premium' => true,
                'limit' => -1,
                'used' => 0,
                'remaining' => -1,
                'reset_date' => $resetDate,
            ];
        }

        $currentMonth = Carbon::now()->format('Y-m');
        $used = VoiceUsageLog::where('user_id', $user->id)
            ->where('calendar_month', $currentMonth)
            ->count();

        $remaining = max(0, self::FREE_MONTHLY_LIMIT - $used);

        return [
            'is_premium' => false,
            'limit' => self::FREE_MONTHLY_LIMIT,
            'used' => $used,
            'remaining' => $remaining,
            'reset_date' => $resetDate,
        ];
    }

    /**
     * Atomically parse and consume a voice quota slot under row locking.
     *
     * @return array{data: array, quota: array}
     *
     * @throws VoiceQuotaExceededException
     */
    public function parseAndConsume(User $user, string $rawText): array
    {
        $currentMonth = Carbon::now()->format('Y-m');
        $resetDate = Carbon::now()->addMonthNoOverflow()->startOfMonth()->toIso8601String();

        return DB::transaction(function () use ($user, $rawText, $currentMonth, $resetDate) {
            // Lock user row to serialize concurrent quota evaluations
            User::where('id', $user->id)->lockForUpdate()->first();

            $isPremium = $this->entitlementService->isPremium($user);

            if (! $isPremium) {
                $used = VoiceUsageLog::where('user_id', $user->id)
                    ->where('calendar_month', $currentMonth)
                    ->count();

                if ($used >= self::FREE_MONTHLY_LIMIT) {
                    throw new VoiceQuotaExceededException;
                }
            }

            // Parse text via deterministic regex NLP parser
            $parsedPayload = $this->parserService->parse($user, $rawText);

            // Record usage log
            VoiceUsageLog::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'raw_text' => $rawText,
                'parsed_payload' => $parsedPayload,
                'calendar_month' => $currentMonth,
                'created_at' => Carbon::now(),
            ]);

            if ($isPremium) {
                $quota = [
                    'is_premium' => true,
                    'limit' => -1,
                    'used' => 0,
                    'remaining' => -1,
                    'reset_date' => $resetDate,
                ];
            } else {
                $newUsed = VoiceUsageLog::where('user_id', $user->id)
                    ->where('calendar_month', $currentMonth)
                    ->count();

                $quota = [
                    'is_premium' => false,
                    'limit' => self::FREE_MONTHLY_LIMIT,
                    'used' => $newUsed,
                    'remaining' => max(0, self::FREE_MONTHLY_LIMIT - $newUsed),
                    'reset_date' => $resetDate,
                ];
            }

            return [
                'data' => $parsedPayload,
                'quota' => $quota,
            ];
        });
    }
}
