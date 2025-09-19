<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'slot_ts',
        'status',
    ];

    protected $casts = [
        'slot_ts' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'slot_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'slot_id');
    }
}