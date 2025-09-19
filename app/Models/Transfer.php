<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'operative_id',
        'stripe_transfer_id',
        'amount',
        'status',
        'settled_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'settled_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function operative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operative_id');
    }
}