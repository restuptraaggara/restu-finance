<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Debt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'wallet_id',
        'type',
        'person_name',
        'amount',
        'paid_amount',
        'due_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'amount' => 'float',
        'paid_amount' => 'float',
        'due_date' => 'date:Y-m-d',
    ];

    protected $appends = [
        'remaining_amount',
        'percentage',
        'is_overdue',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DebtPayment::class)->orderBy('payment_date', 'desc')->orderBy('id', 'desc');
    }

    public function getRemainingAmountAttribute(): float
    {
        return (float) max(0, $this->amount - $this->paid_amount);
    }

    public function getPercentageAttribute(): float
    {
        if ($this->amount <= 0) {
            return 100.0;
        }
        return (float) min(100.0, round(($this->paid_amount / $this->amount) * 100, 1));
    }

    public function getIsOverdueAttribute(): bool
    {
        if ($this->status === 'paid' || empty($this->due_date)) {
            return false;
        }
        return $this->due_date->format('Y-m-d') < now()->format('Y-m-d');
    }
}
