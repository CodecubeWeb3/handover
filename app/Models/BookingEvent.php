<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'booking_id',
        'event_uuid',
        'event_type',
        'payload_json',
        'chain_index',
        'prev_hash',
        'this_hash',
        'created_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}