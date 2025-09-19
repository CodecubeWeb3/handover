<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachment extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'storage_disk',
        'storage_path',
        'mime',
        'bytes',
        'created_at',
    ];

    protected $casts = [
        'bytes' => 'integer',
        'created_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}