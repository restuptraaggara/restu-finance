<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'opening_balance',
        'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'float',
        'is_active' => 'boolean',
    ];

    /**
     * Relasi ke model User (pemilik dompet)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Menghitung ringkasan saldo dinamis wallet berdasarkan transaksi yang tercatat.
     * Saldo = initial_balance + total_income - total_expense
     */
    public function getBalanceDetails(?User $user = null): array
    {
        $owner = $user ?? $this->user;

        if (!$owner) {
            $initial = (float) $this->opening_balance;
            return [
                'initial_balance' => $initial,
                'opening_balance' => $initial,
                'total_income' => 0.0,
                'total_expense' => 0.0,
                'current_balance' => $initial,
                'transactions_count' => 0,
            ];
        }

        $txQuery = $owner->transactions()->where('wallet', $this->name);

        $totalIncome = (float) (clone $txQuery)->where('type', 'income')->sum('amount');
        $totalExpense = (float) (clone $txQuery)->where('type', 'expense')->sum('amount');
        $initialBalance = (float) $this->opening_balance;
        $currentBalance = $initialBalance + $totalIncome - $totalExpense;
        $count = (clone $txQuery)->count();

        return [
            'initial_balance' => $initialBalance,
            'opening_balance' => $initialBalance,
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'current_balance' => $currentBalance,
            'transactions_count' => $count,
        ];
    }

    /**
     * Mengembalikan representasi array wallet yang dilengkapi dengan rincian saldo dinamis.
     */
    public function toArrayWithBalance(?User $user = null): array
    {
        $base = $this->toArray();
        $stats = $this->getBalanceDetails($user);

        return array_merge($base, $stats);
    }
}
