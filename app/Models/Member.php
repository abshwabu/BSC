<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'role_title_am',
        'role_title_en',
        'position_key',
        'default_weights',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_weights' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the report member pivot records for this member.
     */
    public function reportMembers(): HasMany
    {
        return $this->hasMany(ReportMember::class);
    }

    /**
     * Get reports that include this member.
     */
    public function reports(): BelongsToMany
    {
        return $this->belongsToMany(Report::class, 'report_members')
            ->withPivot(['id', 'weights', 'achievements', 'challenges', 'next_steps', 'signed'])
            ->withTimestamps();
    }
}
