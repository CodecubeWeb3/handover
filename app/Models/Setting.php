<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'scope_type',
        'scope_id',
        'key',
        'value',
    ];

    protected $casts = [
        'scope_id' => 'integer',
        'value' => 'array',
    ];
}