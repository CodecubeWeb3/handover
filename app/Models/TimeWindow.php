<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeWindow extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'start_ts',
        'end_ts',
        'created_at',
    ];

    protected $casts = [
        'start_ts' => 'datetime',
        'end_ts' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }
}