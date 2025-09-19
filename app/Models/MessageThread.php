<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageThread extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function readStates(): HasMany
    {
        return $this->hasMany(MessageReadState::class, 'thread_id');
    }

    public function typingStates(): HasMany
    {
        return $this->hasMany(MessageTypingState::class, 'thread_id');
    }
}