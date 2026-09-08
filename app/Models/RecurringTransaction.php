<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'wallet',
        'category',
        'type',
        'amount',
        'description',
        'frequency',
        'next_date',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'float',
        'is_active' => 'boolean',
        'next_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function walletModel(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet', 'name');
    }

    public function categoryModel(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category', 'name');
    }

    /**
     * Calculate the next run date after the given date based on frequency.
     */
    public function calculateNextDate(\DateTimeInterface|string $fromDate): \Carbon\Carbon
    {
        $date = \Carbon\Carbon::parse($fromDate);

        return match ($this->frequency) {
            'daily' => $date->addDay(),
            'weekly' => $date->addWeek(),
            'monthly' => $date->addMonth(),
            'yearly' => $date->addYear(),
            default => $date->addMonth(),
        };
    }

    /**
     * Check whether this recurring transaction is expired (past end_date).
     */
    public function isExpired(): bool
    {
        if (!$this->end_date) {
            return false;
        }
        return \Carbon\Carbon::today()->gt($this->end_date);
    }

    /**
     * Check whether this recurring transaction is due to be processed on or before today.
     */
    public function isDue(): bool
    {
        if (!$this->is_active || !$this->next_date) {
            return false;
        }
        if ($this->isExpired()) {
            return false;
        }
        return \Carbon\Carbon::parse($this->next_date)->lte(\Carbon\Carbon::today());
    }

    /**
     * Return a serializable array for API responses.
     */
    public function toArrayForApi(): array
    {
        return [
            'id' => $this->id,
            'wallet' => $this->wallet,
            'category' => $this->category,
            'type' => $this->type,
            'amount' => $this->amount,
            'description' => $this->description,
            'frequency' => $this->frequency,
            'next_date' => $this->next_date?->toDateString(),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
