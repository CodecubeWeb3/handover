<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageMute extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'thread_id',
        'user_id',
        'muted_until',
        'created_at',
    ];

    protected $casts = [
        'muted_until' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}