<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotMessage extends Model
{
    protected $fillable = [
        'sender',
        'message',
        'intent',
        'metadata_json',
    ];

    protected $casts = [
        'metadata_json' => 'array',
    ];
}
