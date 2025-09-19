<?php

namespace App\Models;

use App\Domain\Payments\Enums\PaymentIntentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentIntent extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'payer_id',
        'role',
        'stripe_pi_id',
        'client_secret',
        'amount_auth',
        'amount_captured',
        'app_fee_piece',
        'status',
        'last_status',
        'last_error',
    ];

    protected $casts = [
        'amount_auth' => 'integer',
        'amount_captured' => 'integer',
        'app_fee_piece' => 'integer',
        'status' => PaymentIntentStatus::class,
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'booking_id', 'booking_id');
    }
}