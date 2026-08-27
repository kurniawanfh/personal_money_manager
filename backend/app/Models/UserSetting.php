<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'user_id',
        'drip_max_single_amount',
        'drip_monthly_threshold',
        'surge_percentage_threshold',
        'zombie_inactivity_days',
    ];

    protected function casts(): array
    {
        return [
            'drip_max_single_amount' => 'decimal:2',
            'drip_monthly_threshold' => 'decimal:2',
            'surge_percentage_threshold' => 'decimal:2',
            'zombie_inactivity_days' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
