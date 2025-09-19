<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Operative extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'kyc_status',
        'reliability_score',
        'stripe_connect_id',
        'languages',
        'bio',
    ];

    protected $casts = [
        'languages' => 'array',
        'reliability_score' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}