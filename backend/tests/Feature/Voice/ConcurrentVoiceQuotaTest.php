<?php

namespace Tests\Feature\Voice;

use App\Exceptions\VoiceQuotaExceededException;
use App\Models\User;
use App\Models\VoiceUsageLog;
use App\Services\VoiceQuotaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConcurrentVoiceQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_concurrent_quota_enforcement_prevents_oversubscription(): void
    {
        $currentMonth = Carbon::now()->format('Y-m');

        // Seed 9 logs: exactly 1 remaining quota slot
        for ($i = 0; $i < 9; $i++) {
            VoiceUsageLog::create([
                'id' => (string) Str::uuid(),
                'user_id' => $this->user->id,
                'raw_text' => "Log {$i}",
                'parsed_payload' => ['amount' => 10000],
                'calendar_month' => $currentMonth,
                'created_at' => Carbon::now(),
            ]);
        }

        $quotaService = app(VoiceQuotaService::class);

        $successCount = 0;
        $rejectedCount = 0;

        // Attempt 5 sequential/parallel consumption attempts
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $quotaService->parseAndConsume($this->user, 'Beli kopi 25k');
                $successCount++;
            } catch (VoiceQuotaExceededException $e) {
                $rejectedCount++;
            }
        }

        // Strictly 1 should succeed, exactly 4 must be rejected
        $this->assertEquals(1, $successCount);
        $this->assertEquals(4, $rejectedCount);

        // Total recorded logs in DB must be exactly 10
        $totalLogs = VoiceUsageLog::where('user_id', $this->user->id)
            ->where('calendar_month', $currentMonth)
            ->count();
        $this->assertEquals(10, $totalLogs);
    }
}
