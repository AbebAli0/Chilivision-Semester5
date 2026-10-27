<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SoilMoistureReading extends Model
{
    protected $fillable = [
        'moisture_percent',
        'adc_value',
        'status',
        'recommendation',
        'source',
        'sent_to_web',
        'sent_to_chatbot',
    ];

    protected $casts = [
        'moisture_percent' => 'float',
        'adc_value' => 'integer',
        'sent_to_web' => 'boolean',
        'sent_to_chatbot' => 'boolean',
    ];
}
