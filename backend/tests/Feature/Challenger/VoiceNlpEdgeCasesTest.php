<?php

namespace Tests\Feature\Challenger;

use App\Models\User;
use App\Models\VoiceUsageLog;
use App\Models\Wallet;
use App\Services\VoiceParserService;
use App\Services\VoiceQuotaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VoiceNlpEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->wallet = Wallet::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'GoPay',
            'type' => 'ewallet',
            'currency' => 'IDR',
            'current_balance' => 500000,
        ]);
    }

    public function test_calendar_month_boundary_resets_quota(): void
    {
        $lastMonth = Carbon::now()->subMonth()->format('Y-m');

        // Seed 10 logs in previous month
        for ($i = 0; $i < 10; $i++) {
            VoiceUsageLog::create([
                'id' => (string) Str::uuid(),
                'user_id' => $this->user->id,
                'raw_text' => "Previous month {$i}",
                'parsed_payload' => ['amount' => 10000],
                'calendar_month' => $lastMonth,
                'created_at' => Carbon::now()->subMonth(),
            ]);
        }

        $quotaService = app(VoiceQuotaService::class);
        $quota = $quotaService->getQuota($this->user);

        // Current month quota should be full 10 remaining
        $this->assertEquals(10, $quota['remaining']);
        $this->assertEquals(0, $quota['used']);

        // Request passes successfully in new month
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/voice/parse', [
            'raw_text' => 'Beli bensin 25k',
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('quota.remaining', 9)
            ->assertJsonPath('quota.used', 1);
    }

    public function test_adversarial_amounts_and_noisy_stt_text(): void
    {
        $service = app(VoiceParserService::class);

        // Comma decimal million
        $res1 = $service->parse($this->user, 'Transfer bonus kerja 2,5 jt masuk dompet');
        $this->assertEquals(2500000, $res1['amount']);
        $this->assertEquals('income', $res1['intent']);

        // Decimal thousand
        $res2 = $service->parse($this->user, 'Beli camilan 12.5k di indomaret');
        $this->assertEquals(12500, $res2['amount']);
        $this->assertEquals('expense', $res2['intent']);

        // Foreign currency mention
        $res3 = $service->parse($this->user, 'Bayar cloud server 20 usd');
        $this->assertEquals(20, $res3['amount']);
        $this->assertEquals('USD', $res3['currency']);
    }

    public function test_invalid_or_malformed_voice_inputs_handled_gracefully(): void
    {
        // Empty raw_text
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/voice/parse', ['raw_text' => ''])
            ->assertStatus(422);

        // Single character text
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/voice/parse', ['raw_text' => 'a'])
            ->assertStatus(422);

        // Non-string
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/voice/parse', ['raw_text' => ['complex']])
            ->assertStatus(422);
    }

    public function test_cross_user_quota_isolation(): void
    {
        $otherUser = User::factory()->create();
        $currentMonth = Carbon::now()->format('Y-m');

        // Max out otherUser's quota
        for ($i = 0; $i < 10; $i++) {
            VoiceUsageLog::create([
                'id' => (string) Str::uuid(),
                'user_id' => $otherUser->id,
                'raw_text' => "Log {$i}",
                'parsed_payload' => ['amount' => 10000],
                'calendar_month' => $currentMonth,
                'created_at' => Carbon::now(),
            ]);
        }

        // otherUser is blocked
        $this->actingAs($otherUser, 'sanctum')
            ->postJson('/api/v1/voice/parse', ['raw_text' => 'Kopi 20k'])
            ->assertStatus(429);

        // our user has clean quota and succeeds
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/voice/parse', ['raw_text' => 'Kopi 20k'])
            ->assertStatus(200)
            ->assertJsonPath('quota.remaining', 9);
    }

    public function test_zero_external_ai_dependencies_in_codebase(): void
    {
        $disallowedKeywords = ['openai', 'anthropic', 'api.groq.com', 'api.together.xyz', 'chatgpt.com/api'];
        $dirsToScan = [base_path('app'), base_path('config'), base_path('routes')];

        foreach ($dirsToScan as $dir) {
            foreach ($disallowedKeywords as $kw) {
                $grepResult = shell_exec("grep -rnI '{$kw}' ".escapeshellarg($dir));
                $this->assertEmpty($grepResult, "Disallowed external AI dependency keyword found in {$dir}: {$kw}");
            }
        }
    }
}
