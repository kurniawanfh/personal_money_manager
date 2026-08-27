<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_wallet_id' => ['required', 'uuid', 'different:target_wallet_id', 'exists:wallets,id'],
            'target_wallet_id' => ['required', 'uuid', 'different:source_wallet_id', 'exists:wallets,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'transaction_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'target_wallet_id.different' => 'Source and target wallet cannot be the same.',
            'source_wallet_id.different' => 'Source and target wallet cannot be the same.',
        ];
    }
}
