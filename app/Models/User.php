<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'password',
        'theme',
        'currency',
        'language',
        'is_admin',
    ];

    protected $casts = [
        'is_admin' => 'boolean',
    ];

    /**
     * Field yang disembunyikan saat serialisasi JSON/Array agar data sensitif tidak bocor.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function wallets() { return $this->hasMany(Wallet::class); }
    public function categories() { return $this->hasMany(Category::class); }
    public function transactions() { return $this->hasMany(Transaction::class); }
    public function budgets() { return $this->hasMany(Budget::class); }
    public function goals() { return $this->hasMany(Goal::class); }
    public function recurringTransactions() { return $this->hasMany(RecurringTransaction::class); }
    public function debts() { return $this->hasMany(Debt::class); }
    public function feedbacks() { return $this->hasMany(Feedback::class); }
    public function chatMessages() { return $this->hasMany(ChatMessage::class); }
}
