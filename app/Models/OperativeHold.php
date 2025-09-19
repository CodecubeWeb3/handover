<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperativeHold extends Model
{
    use HasFactory;

    protected $fillable = [
        'operative_id',
        'slot_ts',
        'expires_at',
    ];

    protected $casts = [
        'slot_ts' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function operative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operative_id');
    }
}