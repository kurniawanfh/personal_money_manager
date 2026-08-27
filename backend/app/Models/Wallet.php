<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Wallet extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'id',
        'user_id',
        'name',
        'type',
        'currency',
        'initial_balance',
        'current_balance',
        'is_archived',
        'color',
        'icon',
        'server_revision',
    ];

    protected $appends = ['balance'];

    protected function casts(): array
    {
        return [
            'initial_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'is_archived' => 'boolean',
            'server_revision' => 'integer',
        ];
    }

    protected function balance(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => isset($attributes['current_balance']) ? (float) $attributes['current_balance'] : 0.0,
            set: fn ($value) => ['current_balance' => $value],
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'wallet_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(Transaction::class, 'transfer_target_wallet_id');
    }
}
