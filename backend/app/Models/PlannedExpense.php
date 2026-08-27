<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PlannedExpense extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'user_id',
        'subscription_id',
        'wallet_id',
        'category_id',
        'estimated_idr_amount',
        'actual_idr_amount',
        'due_date',
        'billing_cycle_key',
        'status',
        'confirmed_at',
        'server_revision',
    ];

    protected function casts(): array
    {
        return [
            'estimated_idr_amount' => 'decimal:2',
            'actual_idr_amount' => 'decimal:2',
            'due_date' => 'date',
            'confirmed_at' => 'datetime',
            'server_revision' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'planned_expense_id');
    }
}
