<?php

namespace App\Models;

use App\Support\Geo\PointNormalizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Request extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'meet_point',
        'notes',
        'status',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function timeWindows(): HasMany
    {
        return $this->hasMany(TimeWindow::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(BookingSlot::class);
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    public function meetingCoordinates(): ?array
    {
        return PointNormalizer::normalize($this->meet_point);
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    public function latestMeetPoint(): ?array
    {
        if ($coords = $this->meetingCoordinates()) {
            return $coords;
        }

        $latestBooking = $this->slots()
            ->latest('slot_ts')
            ->first()?->bookings()
            ->latest('created_at')
            ->first();

        return $latestBooking?->meetingCoordinates();
    }
}