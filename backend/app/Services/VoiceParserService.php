<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;

class VoiceParserService
{
    /**
     * Parse raw voice STT transcription text into structured financial transaction payload.
     *
     * @return array{
     *     intent: string,
     *     amount: float,
     *     currency: string,
     *     wallet_id: ?string,
     *     wallet_name: ?string,
     *     category_id: ?string,
     *     category_name: ?string,
     *     date: string,
     *     description: string,
     *     confidence: float
     * }
     */
    public function parse(User $user, string $rawText): array
    {
        $normalizedText = mb_strtolower(trim($rawText));

        // 1. Detect Intent
        $intent = $this->extractIntent($normalizedText);

        // 2. Extract Amount & Currency
        $amountData = $this->extractAmount($normalizedText);
        $amount = $amountData['amount'];
        $currency = $amountData['currency'];

        // 3. Extract Date
        $date = $this->extractDate($normalizedText);

        // 4. Match Wallet
        $walletData = $this->matchWallet($user, $normalizedText);

        // 5. Match Category
        $categoryData = $this->matchCategory($user, $normalizedText, $intent);

        // 6. Clean Description
        $description = $this->extractDescription($rawText, $amountData['raw_match'] ?? '');

        // 7. Calculate Confidence Score
        $confidence = 0.5;
        if ($amount > 0) {
            $confidence += 0.2;
        }
        if (! empty($walletData['id'])) {
            $confidence += 0.15;
        }
        if (! empty($categoryData['id'])) {
            $confidence += 0.15;
        }
        $confidence = min(1.0, round($confidence, 2));

        return [
            'intent' => $intent,
            'amount' => $amount,
            'currency' => $currency,
            'wallet_id' => $walletData['id'] ?? null,
            'wallet_name' => $walletData['name'] ?? null,
            'category_id' => $categoryData['id'] ?? null,
            'category_name' => $categoryData['name'] ?? null,
            'date' => $date,
            'description' => $description,
            'confidence' => $confidence,
        ];
    }

    /**
     * Extract intent (expense vs income vs transfer).
     */
    private function extractIntent(string $text): string
    {
        if (preg_match('/\b(gaji|terima|dapat|bonus|cashback|refund|pendapatan|income|masuk)\b/i', $text)) {
            return 'income';
        }

        if (preg_match('/\b(transfer|pindah|tf|kirim)\s+(?:saldo|uang)?\s*(?:ke|menuju)\b/i', $text)) {
            return 'transfer';
        }

        return 'expense';
    }

    /**
     * Extract numeric amount and currency.
     */
    private function extractAmount(string $text): array
    {
        $currency = 'IDR';
        if (preg_match('/\b(usd|dollar|\$)\b/i', $text)) {
            $currency = 'USD';
        } elseif (preg_match('/\b(eur|euro|€)\b/i', $text)) {
            $currency = 'EUR';
        } elseif (preg_match('/\b(sgd|singapore dollar)\b/i', $text)) {
            $currency = 'SGD';
        }

        // Pattern 1: e.g. "25k", "25.5k", "50rb", "50 rb", "50 ribu", "1.5jt", "1,5 jt", "2 juta", "1m", "1 milyar"
        if (preg_match('/(?:rp\.?\s*)?(\d+(?:[.,]\d+)?)\s*(k|rb|ribu|jt|juta|m|milyar|miliar|b|billion)\b/i', $text, $matches)) {
            $rawMatch = $matches[0];
            $num = (float) str_replace(',', '.', $matches[1]);
            $unit = strtolower($matches[2]);

            $multiplier = match ($unit) {
                'k', 'rb', 'ribu' => 1000,
                'jt', 'juta' => 1000000,
                'm', 'milyar', 'miliar', 'b', 'billion' => 1000000000,
                default => 1,
            };

            return [
                'amount' => round($num * $multiplier, 2),
                'currency' => $currency,
                'raw_match' => $rawMatch,
            ];
        }

        // Pattern 2: e.g. "Rp 25.000", "Rp. 50000", "150000"
        if (preg_match('/(?:rp\.?\s*)?(\d{1,3}(?:[.]\d{3})+(?:,\d{2})?|\d+(?:[.,]\d{2})?)/i', $text, $matches)) {
            $rawMatch = $matches[0];
            $cleanNum = str_replace('.', '', $matches[1]);
            $cleanNum = str_replace(',', '.', $cleanNum);

            return [
                'amount' => round((float) $cleanNum, 2),
                'currency' => $currency,
                'raw_match' => $rawMatch,
            ];
        }

        return [
            'amount' => 0.0,
            'currency' => $currency,
            'raw_match' => '',
        ];
    }

    /**
     * Extract date from relative time terms.
     */
    private function extractDate(string $text): string
    {
        if (preg_match('/\b(kemarin|yesterday)\b/i', $text)) {
            return Carbon::yesterday()->toDateString();
        }

        if (preg_match('/\b(lusa|2 hari lalu|dua hari lalu)\b/i', $text)) {
            return Carbon::now()->subDays(2)->toDateString();
        }

        if (preg_match('/\b(besok|tomorrow)\b/i', $text)) {
            return Carbon::tomorrow()->toDateString();
        }

        return Carbon::today()->toDateString();
    }

    /**
     * Match user wallet.
     */
    private function matchWallet(User $user, string $text): array
    {
        $wallets = $user->wallets()->get();

        foreach ($wallets as $wallet) {
            $walletName = mb_strtolower($wallet->name);
            if (str_contains($text, $walletName)) {
                return ['id' => $wallet->id, 'name' => $wallet->name];
            }
        }

        // Keyword dictionary fallback
        $keywordMap = [
            'gopay' => 'GoPay',
            'bca' => 'BCA',
            'cash' => 'Cash',
            'tunai' => 'Cash',
            'dompet' => 'Cash',
            'ovo' => 'OVO',
            'dana' => 'DANA',
            'mandiri' => 'Mandiri',
            'bri' => 'BRI',
            'bni' => 'BNI',
            'jago' => 'Bank Jago',
            'shopeepay' => 'ShopeePay',
            'spay' => 'ShopeePay',
        ];

        foreach ($keywordMap as $kw => $name) {
            if (str_contains($text, $kw)) {
                $matchedWallet = $wallets->first(fn ($w) => stripos($w->name, $kw) !== false || stripos($w->name, $name) !== false);
                if ($matchedWallet) {
                    return ['id' => $matchedWallet->id, 'name' => $matchedWallet->name];
                }
            }
        }

        return ['id' => null, 'name' => null];
    }

    /**
     * Match transaction category.
     */
    private function matchCategory(User $user, string $text, string $intent): array
    {
        $categories = Category::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhereNull('user_id');
        })->where('type', $intent === 'income' ? 'income' : 'expense')->get();

        foreach ($categories as $cat) {
            $catName = mb_strtolower($cat->name);
            if (str_contains($text, $catName)) {
                return ['id' => $cat->id, 'name' => $cat->name];
            }
        }

        // Keyword dictionary
        $categoryKeywords = [
            'Food & Beverage' => ['kopi', 'coffee', 'makan', 'lunch', 'dinner', 'breakfast', 'sarapan', 'resto', 'kafe', 'cafe', 'warteg', 'snack', 'jajan', 'kuliner', 'bakso', 'mie', 'sate', 'nasi'],
            'Transportation' => ['bensin', 'bbm', 'pertalite', 'pertamax', 'parkir', 'tol', 'gojek', 'grab', 'ojol', 'taksi', 'taxi', 'kereta', 'krl', 'mrt', 'transjakarta', 'busway'],
            'Bills & Utilities' => ['listrik', 'pln', 'pdam', 'air', 'wifi', 'indihome', 'biznet', 'pulsa', 'kuota', 'token', 'tagihan', 'iuran'],
            'Salary' => ['gaji', 'salary', 'payroll', 'upah', 'honor'],
            'Entertainment' => ['netflix', 'spotify', 'youtube', 'game', 'steam', 'bioskop', 'cinema', 'nonton', 'ps5', 'disney'],
            'Shopping' => ['baju', 'sepatu', 'belanja', 'tokopedia', 'shopee', 'lazada', 'mall', 'minimarket', 'indomaret', 'alfamart', 'supermarket'],
            'Health' => ['obat', 'dokter', 'apotek', 'klinik', 'rs', 'rumah sakit', 'vitamin', 'medis'],
        ];

        foreach ($categoryKeywords as $targetCatName => $keywords) {
            foreach ($keywords as $kw) {
                if (preg_match('/\b'.preg_quote($kw, '/').'\b/i', $text)) {
                    $matchedCat = $categories->first(fn ($c) => stripos($c->name, $targetCatName) !== false || stripos($c->name, $kw) !== false);
                    if ($matchedCat) {
                        return ['id' => $matchedCat->id, 'name' => $matchedCat->name];
                    }
                }
            }
        }

        // Return first default or null
        return ['id' => $categories->first()?->id, 'name' => $categories->first()?->name];
    }

    /**
     * Clean description.
     */
    private function extractDescription(string $rawText, string $amountMatch): string
    {
        $text = $rawText;

        if ($amountMatch) {
            $text = str_ireplace($amountMatch, '', $text);
        }

        // Clean common temporal and payment conjunctions
        $text = preg_replace('/\b(kemarin|hari ini|tadi|barusan|lusa|besok)\b/i', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return ucfirst(trim($text)) ?: 'Voice Transaction';
    }
}
