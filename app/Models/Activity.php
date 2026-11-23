<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'report_member_id',
        'sort_order',
        'activity_text',
        'kpi_text',
        'target_value',
        'target_label',
        'actual_value',
        'actual_label',
        'direction',
        'perspective',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'target_value' => 'decimal:2',
            'actual_value' => 'decimal:2',
        ];
    }

    /**
     * Get the report member this activity belongs to.
     */
    public function reportMember(): BelongsTo
    {
        return $this->belongsTo(ReportMember::class);
    }
}
