<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'thread_id',
        'sender_id',
        'body',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    public function flags(): HasMany
    {
        return $this->hasMany(MessageFlag::class);
    }
}