<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sender_id',
        'is_admin_reply',
        'message',
        'is_read',
    ];

    protected $casts = [
        'is_admin_reply' => 'boolean',
        'is_read' => 'boolean',
    ];

    /**
     * User pemilik percakapan (klien).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * User yang mengirim pesan (bisa klien atau admin).
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}