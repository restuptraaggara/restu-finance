<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'wallet',
        'category',
        'type',
        'amount',
        'description',
        'method',
        'date',
        'time',
        'note',
        'goal',
    ];

    protected $casts = [
        'amount' => 'float',
        'date' => 'date:Y-m-d',
    ];

    /**
     * Relasi ke model User (pemilik transaksi)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
