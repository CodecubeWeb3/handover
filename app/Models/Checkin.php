<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Checkin extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'booking_id',
        'user_id',
        'kind',
        'lat',
        'lng',
        'accuracy_m',
        'token_id',
        'device_attested',
        'note',
        'created_at',
    ];

    protected $casts = [
        'device_attested' => 'boolean',
        'lat' => 'float',
        'lng' => 'float',
        'created_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}