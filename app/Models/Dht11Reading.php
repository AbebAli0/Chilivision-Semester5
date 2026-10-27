<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dht11Reading extends Model
{
    protected $fillable = [
        'temperature',
        'humidity',
        'heat_index',
        'status',
        'recommendation',
        'source',
        'sent_to_web',
        'sent_to_chatbot',
    ];

    protected $casts = [
        'temperature' => 'float',
        'humidity' => 'float',
        'heat_index' => 'float',
        'sent_to_web' => 'boolean',
        'sent_to_chatbot' => 'boolean',
    ];
}
