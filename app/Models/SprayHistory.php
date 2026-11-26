<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SprayHistory extends Model
{
    protected $fillable = [
        'type',
        'trigger_mode',
        'triggered_by',
        'duration_seconds',
        'status',
        'leaf_condition',
        'soil_moisture',
        'notes',
        'sprayed_at',
    ];

    protected $casts = [
        'duration_seconds' => 'integer',
        'soil_moisture' => 'float',
        'sprayed_at' => 'datetime',
    ];
}
