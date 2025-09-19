<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitlistEntry extends Model
{
    use HasFactory;

    protected $table = 'waitlist';

    public $timestamps = false;

    protected $fillable = [
        'slot_id',
        'operative_id',
        'position',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(BookingSlot::class, 'slot_id');
    }

    public function operative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operative_id');
    }
}