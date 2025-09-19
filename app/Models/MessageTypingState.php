<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageTypingState extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'thread_id',
        'user_id',
        'state',
        'updated_at',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class, 'thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}