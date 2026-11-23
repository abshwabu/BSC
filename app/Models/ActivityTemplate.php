<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'position_key',
        'title',
        'activity_text',
        'kpi_text',
        'default_target_value',
        'default_target_label',
        'direction',
        'perspective',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'default_target_value' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }
}
