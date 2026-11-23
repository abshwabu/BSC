<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportMember extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'report_id',
        'member_id',
        'weights',
        'achievements',
        'challenges',
        'next_steps',
        'signed',
        'readiness_note',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weights' => 'array',
            'signed' => 'boolean',
        ];
    }

    /**
     * Get the report this record belongs to.
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * Get the member this record belongs to.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get the activities for this report member.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->orderBy('sort_order');
    }
}
