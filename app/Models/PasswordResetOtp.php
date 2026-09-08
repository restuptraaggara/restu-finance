<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasswordResetOtp extends Model
{
    use HasFactory;

    protected $table = 'password_reset_otps';

    protected $fillable = [
        'email',
        'otp',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Memeriksa apakah token OTP sudah kedaluwarsa.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Memeriksa apakah kode OTP cocok dan belum kedaluwarsa.
     */
    public function isValid(string $otp): bool
    {
        return !$this->isExpired() && hash_equals($this->otp, trim($otp));
    }
}

