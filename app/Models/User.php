<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builders\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;

    protected $guard_name = 'web';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'country',
        'dob',
        'stripe_customer_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'dob' => 'date',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function scopeOfRole(Builder $query, UserRole|string $role): Builder
    {
        $value = $role instanceof UserRole ? $role->value : $role;

        return $query->where('role', $value);
    }

    public function operative(): HasOne
    {
        return $this->hasOne(Operative::class);
    }

    public function parentProfile(): HasOne
    {
        return $this->hasOne(ParentProfile::class, 'user_id');
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(Verification::class);
    }

    public function sanctions(): HasMany
    {
        return $this->hasMany(Sanction::class);
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(Request::class, 'parent_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'operative_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function messageFlags(): HasMany
    {
        return $this->hasMany(MessageFlag::class, 'reporter_id');
    }

    public function paymentIntents(): HasMany
    {
        return $this->hasMany(PaymentIntent::class, 'payer_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'operative_id');
    }
}