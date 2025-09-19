<?php

namespace App\Models;

use App\Support\Geo\PointNormalizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    private ?CountryProfile $cachedCountryProfile = null;

    protected $fillable = [
        'slot_id',
        'operative_id',
        'slot_ts',
        'status',
        'meet_qr',
        'meet_point',
        'buffer_minutes',
        'geofence_radius_m',
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

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(BookingEvent::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function handoverTokens(): HasMany
    {
        return $this->hasMany(HandoverToken::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function transfer(): HasOne
    {
        return $this->hasOne(Transfer::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    public function messageThread(): HasOne
    {
        return $this->hasOne(MessageThread::class);
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    public function meetingCoordinates(): ?array
    {
        $coords = PointNormalizer::normalize($this->meet_point);

        if ($coords !== null) {
            return $coords;
        }

        return $this->slot?->request?->meetingCoordinates();
    }

    public function countryProfile(): CountryProfile
    {
        if ($this->cachedCountryProfile instanceof CountryProfile) {
            return $this->cachedCountryProfile;
        }

        $country = $this->slot?->request?->parent?->country
            ?? $this->operative?->country
            ?? 'GB';

        return $this->cachedCountryProfile = CountryProfile::query()
            ->where('country', $country)
            ->firstOrFail();
    }
}