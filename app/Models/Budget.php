<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category',
        'limit_amount',
    ];

    protected $casts = [
        'limit_amount' => 'float',
    ];

    /**
     * Relasi ke model User (pemilik budget)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Menghitung penggunaan anggaran secara dinamis berdasarkan transaksi pengeluaran (expense)
     * pada kategori dan periode (bulan) yang bersangkutan.
     */
    public function getUsageDetails(?User $user = null, ?string $month = null): array
    {
        $owner = $user ?? $this->user;
        $period = $month ?: now()->format('Y-m');
        $limit = (float) $this->limit_amount;

        if (!$owner) {
            return [
                'limit' => $limit,
                'limit_amount' => $limit,
                'used' => 0.0,
                'remaining' => $limit,
                'percentage' => 0.0,
                'is_over_budget' => false,
                'period' => $period,
                'transactions_count' => 0,
            ];
        }

        // Hanya hitung transaksi bertipe 'expense' pada kategori ini dan di bulan yang sama
        $txQuery = $owner->transactions()
            ->where('category', $this->category)
            ->where('type', 'expense')
            ->where('date', 'like', $period . '%');

        $used = (float) (clone $txQuery)->sum('amount');
        $remaining = max(0, $limit - $used);
        $percentage = $limit > 0 ? round(($used / $limit) * 100, 2) : 0.0;
        $isOver = $used > $limit;
        $count = (clone $txQuery)->count();

        return [
            'limit' => $limit,
            'limit_amount' => $limit,
            'used' => $used,
            'remaining' => $remaining,
            'percentage' => $percentage,
            'is_over_budget' => $isOver,
            'period' => $period,
            'transactions_count' => $count,
        ];
    }

    /**
     * Mengembalikan representasi array budget yang dilengkapi dengan data kalkulasi usage.
     */
    public function toArrayWithUsage(?User $user = null, ?string $month = null): array
    {
        $base = $this->toArray();
        $usage = $this->getUsageDetails($user, $month);

        return array_merge($base, $usage);
    }
}
