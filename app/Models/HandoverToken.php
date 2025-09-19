<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HandoverToken extends Model
{
    use HasFactory;

    public const LEG_A = 'A';
    public const LEG_B = 'B';

    public $timestamps = false;

    protected $fillable = [
        'booking_id',
        'leg',
        'totp_secret_hash',
        'offline_pin_encrypted',
        'rotated_at',
        'used_at',
        'created_at',
    ];

    protected $casts = [
        'rotated_at' => 'datetime',
        'used_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected $hidden = [
        'totp_secret_hash',
        'offline_pin_encrypted',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}