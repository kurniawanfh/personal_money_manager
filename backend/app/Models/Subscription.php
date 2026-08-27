<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'user_id',
        'wallet_id',
        'category_id',
        'name',
        'original_currency',
        'original_amount',
        'estimated_idr_amount',
        'billing_cycle',
        'billing_day',
        'next_billing_date',
        'remind_h3',
        'remind_h1',
        'status',
        'server_revision',
    ];

    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'estimated_idr_amount' => 'decimal:2',
            'billing_day' => 'integer',
            'next_billing_date' => 'date',
            'remind_h3' => 'boolean',
            'remind_h1' => 'boolean',
            'server_revision' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function plannedExpenses(): HasMany
    {
        return $this->hasMany(PlannedExpense::class);
    }
}
