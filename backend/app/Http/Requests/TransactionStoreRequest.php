<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'wallet_id' => [
                'required',
                'uuid',
                Rule::exists('wallets', 'id')->where('user_id', $userId),
            ],
            'category_id' => [
                'nullable',
                'uuid',
                Rule::exists('categories', 'id')->where(function ($query) use ($userId) {
                    $query->whereNull('user_id')
                        ->orWhere('user_id', $userId);
                }),
            ],
            'type' => ['required', 'string', 'in:expense,income,transfer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'foreign_amount' => ['nullable', 'numeric', 'min:0'],
            'foreign_currency' => ['nullable', 'string', 'max:10'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'transfer_target_wallet_id' => [
                'nullable',
                'uuid',
                Rule::exists('wallets', 'id')->where('user_id', $userId),
            ],
            'target_wallet_id' => [
                'nullable',
                'uuid',
                Rule::exists('wallets', 'id')->where('user_id', $userId),
            ],
            'transaction_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'attachment_path' => ['nullable', 'string', 'max:500'],
            'is_voice_logged' => ['nullable', 'boolean'],
            'is_excluded_from_stats' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'wallet_id.exists' => 'The selected wallet is invalid or does not belong to you.',
            'transfer_target_wallet_id.exists' => 'The target wallet is invalid or does not belong to you.',
            'target_wallet_id.exists' => 'The target wallet is invalid or does not belong to you.',
            'category_id.exists' => 'The selected category is invalid or does not belong to you.',
        ];
    }
}
