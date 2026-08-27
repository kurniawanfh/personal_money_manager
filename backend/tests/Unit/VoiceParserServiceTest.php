<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\User;
use App\Models\Wallet;
use App\Services\VoiceParserService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VoiceParserServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Wallet $walletGopay;

    protected Wallet $walletBca;

    protected Category $categoryFood;

    protected Category $categoryBills;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->walletGopay = Wallet::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'GoPay',
            'type' => 'ewallet',
            'currency' => 'IDR',
            'current_balance' => 200000,
        ]);
        $this->walletBca = Wallet::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'BCA',
            'type' => 'bank',
            'currency' => 'IDR',
            'current_balance' => 5000000,
        ]);
        $this->categoryFood = Category::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Food & Beverage',
            'type' => 'expense',
        ]);
        $this->categoryBills = Category::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Bills & Utilities',
            'type' => 'expense',
        ]);
    }

    public function test_parses_simple_expense_with_k_suffix_and_wallet(): void
    {
        $service = app(VoiceParserService::class);
        $result = $service->parse($this->user, 'Beli kopi 25k pakai gopay kemarin');

        $this->assertEquals('expense', $result['intent']);
        $this->assertEquals(25000, $result['amount']);
        $this->assertEquals('IDR', $result['currency']);
        $this->assertEquals($this->walletGopay->id, $result['wallet_id']);
        $this->assertEquals($this->categoryFood->id, $result['category_id']);
        $this->assertEquals(Carbon::yesterday()->toDateString(), $result['date']);
        $this->assertGreaterThanOrEqual(0.8, $result['confidence']);
    }

    public function test_parses_jt_suffix_and_income_intent(): void
    {
        $service = app(VoiceParserService::class);
        $result = $service->parse($this->user, 'Terima gaji 15.5jt masuk ke bca hari ini');

        $this->assertEquals('income', $result['intent']);
        $this->assertEquals(15500000, $result['amount']);
        $this->assertEquals('IDR', $result['currency']);
        $this->assertEquals($this->walletBca->id, $result['wallet_id']);
        $this->assertEquals(Carbon::today()->toDateString(), $result['date']);
    }

    public function test_parses_colloquial_indonesian_amounts_rb_and_ribu(): void
    {
        $service = app(VoiceParserService::class);

        $res1 = $service->parse($this->user, 'Bayar listrik 250rb');
        $this->assertEquals(250000, $res1['amount']);
        $this->assertEquals($this->categoryBills->id, $res1['category_id']);

        $res2 = $service->parse($this->user, 'Makan siang 45 ribu');
        $this->assertEquals(45000, $res2['amount']);
        $this->assertEquals($this->categoryFood->id, $res2['category_id']);
    }
}
