<?php

namespace App\Models;

use App\Domain\Payments\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'currency',
        'amount_total',
        'refund_total',
        'refund_reason',
        'refunded_at',
        'platform_fee',
        'late_fee_a',
        'late_fee_b',
        'travel_stipend_a',
        'travel_stipend_b',
        'status',
        'payout_settled_at',
    ];

    protected $casts = [
        'amount_total' => 'integer',
        'refund_total' => 'integer',
        'platform_fee' => 'integer',
        'late_fee_a' => 'integer',
        'late_fee_b' => 'integer',
        'travel_stipend_a' => 'integer',
        'travel_stipend_b' => 'integer',
        'status' => PaymentStatus::class,
        'refunded_at' => 'datetime',
        'payout_settled_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function intents(): HasMany
    {
        return $this->hasMany(PaymentIntent::class);
    }
}