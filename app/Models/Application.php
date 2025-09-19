<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'slot_id',
        'operative_id',
        'slot_ts',
        'status',
    ];

    protected $casts = [
        'slot_ts' => 'datetime',
    ];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(BookingSlot::class);
    }

    public function operative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operative_id');
    }
}