<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\MessageAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageAttachmentFactory extends Factory
{
    protected $model = MessageAttachment::class;

    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'storage_disk' => 's3',
            'storage_path' => 'attachments/'.fake()->uuid().'.jpg',
            'mime' => 'image/jpeg',
            'bytes' => fake()->numberBetween(10_000, 200_000),
            'created_at' => now(),
        ];
    }
}