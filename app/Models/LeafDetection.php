<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeafDetection extends Model
{
    protected $fillable = [
        'model_id',
        'model_name',
        'class_id',
        'class_name',
        'confidence',
        'detection_count',
        'detections_json',
        'image_path',
        'image_hash',
        'perceptual_hash',
        'image_width',
        'image_height',
        'recommendation',
    ];

    protected $casts = [
        'confidence' => 'float',
        'detection_count' => 'integer',
        'detections_json' => 'array',
        'image_width' => 'integer',
        'image_height' => 'integer',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return '/storage/'.ltrim($this->image_path, '/');
    }
}
