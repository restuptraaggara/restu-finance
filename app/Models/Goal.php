<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Goal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'target_amount',
        'saved_amount',
        'deadline',
    ];

    protected $casts = [
        'target_amount' => 'float',
        'saved_amount' => 'float',
        'deadline' => 'date:Y-m-d',
    ];

    /**
     * Relasi ke model User (pemilik goal)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi ke transaksi yang dialokasikan untuk goal ini
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'goal', 'name')
                    ->where('user_id', $this->user_id);
    }

    /**
     * Menghitung progres capaian goal berdasarkan saved_amount dan transaksi terkait.
     */
    public function getProgressDetails(?User $user = null): array
    {
        $owner = $user ?? $this->user;
        $target = (float) $this->target_amount;
        $savedBase = (float) $this->saved_amount;

        if (!$owner) {
            $remaining = max(0, $target - $savedBase);
            $percentage = $target > 0 ? round(($savedBase / $target) * 100, 2) : 0.0;
            return [
                'target' => $target,
                'target_amount' => $target,
                'saved' => $savedBase,
                'saved_amount' => $savedBase,
                'remaining' => $remaining,
                'percentage' => $percentage,
                'is_achieved' => $savedBase >= $target,
                'transactions_count' => 0,
            ];
        }

        $txQuery = $owner->transactions()->where('goal', $this->name);
        $txSaved = (float) (clone $txQuery)->sum('amount');
        $totalSaved = $savedBase + $txSaved;
        $remaining = max(0, $target - $totalSaved);
        $percentage = $target > 0 ? round(($totalSaved / $target) * 100, 2) : 0.0;
        $isAchieved = $totalSaved >= $target;
        $count = (clone $txQuery)->count();

        return [
            'target' => $target,
            'target_amount' => $target,
            'saved' => $totalSaved,
            'saved_amount' => $totalSaved,
            'initial_saved_amount' => $savedBase,
            'transaction_saved_amount' => $txSaved,
            'remaining' => $remaining,
            'percentage' => $percentage,
            'is_achieved' => $isAchieved,
            'transactions_count' => $count,
        ];
    }

    /**
     * Mengembalikan representasi array goal dengan rincian progres capaian.
     */
    public function toArrayWithProgress(?User $user = null): array
    {
        $base = $this->toArray();
        $progress = $this->getProgressDetails($user);

        return array_merge($base, $progress);
    }
}
