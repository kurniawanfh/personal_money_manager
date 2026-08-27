<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'id',
        'user_id',
        'wallet_id',
        'category_id',
        'type',
        'amount',
        'currency',
        'foreign_amount',
        'foreign_currency',
        'exchange_rate',
        'transfer_target_wallet_id',
        'transfer_pair_id',
        'planned_expense_id',
        'transaction_date',
        'description',
        'notes',
        'attachment_path',
        'is_voice_logged',
        'is_excluded_from_stats',
        'server_revision',
    ];

    protected $appends = ['target_wallet_id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'foreign_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'transaction_date' => 'datetime',
            'is_voice_logged' => 'boolean',
            'is_excluded_from_stats' => 'boolean',
            'server_revision' => 'integer',
        ];
    }

    protected function targetWalletId(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => $attributes['transfer_target_wallet_id'] ?? null,
            set: fn ($value) => ['transfer_target_wallet_id' => $value],
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    public function targetWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'transfer_target_wallet_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function plannedExpense(): BelongsTo
    {
        return $this->belongsTo(PlannedExpense::class, 'planned_expense_id');
    }
}
