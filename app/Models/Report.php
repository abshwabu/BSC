<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Report extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'period_label',
        'period_type',
        'year',
        'report_date',
        'status',
        'company_name',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'report_date' => 'date',
        ];
    }

    /**
     * Get the report members for the report.
     */
    public function reportMembers(): HasMany
    {
        return $this->hasMany(ReportMember::class)->orderBy('id');
    }

    /**
     * Get the members associated with the report.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'report_members')
            ->withPivot(['id', 'weights', 'achievements', 'challenges', 'next_steps', 'signed'])
            ->withTimestamps();
    }

    /**
     * Get the activities through report members.
     */
    public function activities(): HasManyThrough
    {
        return $this->hasManyThrough(Activity::class, ReportMember::class);
    }

    /**
     * Get the report decision configuration.
     */
    public function decision(): HasOne
    {
        return $this->hasOne(ReportDecision::class);
    }

    /**
     * Clone this report with its members and activities for the next quarter.
     * Actual values are reset to null.
     */
    public function duplicate(array $attributes = []): self
    {
        return DB::transaction(function () use ($attributes) {
            $nextQuarterMap = [
                'Q1' => ['period_label' => 'Q2', 'year_delta' => 0],
                'Q2' => ['period_label' => 'Q3', 'year_delta' => 0],
                'Q3' => ['period_label' => 'Q4', 'year_delta' => 0],
                'Q4' => ['period_label' => 'Q1', 'year_delta' => 1],
            ];

            $normalizedPeriod = strtoupper(trim((string) $this->period_label));
            $periodConfig = $nextQuarterMap[$normalizedPeriod] ?? [
                'period_label' => $this->period_label,
                'year_delta' => 0,
            ];

            $nextPeriod = $attributes['period_label'] ?? $periodConfig['period_label'];
            $nextYear = $attributes['year'] ?? ($this->year + $periodConfig['year_delta']);

            $defaultTitle = $this->title;
            if (isset($nextQuarterMap[$normalizedPeriod])) {
                $defaultTitle = str_ireplace(
                    [$this->period_label, (string) $this->year],
                    [$nextPeriod, (string) $nextYear],
                    $this->title
                );
            }

            $clonedReport = self::create([
                'title' => $attributes['title'] ?? $defaultTitle,
                'period_label' => $nextPeriod,
                'period_type' => $attributes['period_type'] ?? $this->period_type,
                'year' => $nextYear,
                'report_date' => $attributes['report_date'] ?? null,
                'status' => $attributes['status'] ?? 'draft',
                'company_name' => $attributes['company_name'] ?? $this->company_name,
                'notes' => $attributes['notes'] ?? $this->notes,
            ]);

            $this->loadMissing(['reportMembers.activities', 'decision']);

            foreach ($this->reportMembers as $reportMember) {
                $clonedMember = $clonedReport->reportMembers()->create([
                    'member_id' => $reportMember->member_id,
                    'weights' => $reportMember->weights,
                    'achievements' => null,
                    'challenges' => null,
                    'next_steps' => null,
                    'signed' => false,
                    'readiness_note' => null,
                ]);

                foreach ($reportMember->activities as $activity) {
                    $clonedMember->activities()->create([
                        'sort_order' => $activity->sort_order,
                        'activity_text' => $activity->activity_text,
                        'kpi_text' => $activity->kpi_text,
                        'target_value' => $activity->target_value,
                        'target_label' => $activity->target_label,
                        'actual_value' => null,
                        'actual_label' => null,
                        'direction' => $activity->direction,
                        'perspective' => $activity->perspective,
                    ]);
                }
            }

            if ($this->decision) {
                $clonedReport->decision()->create([
                    'plc_gate_enabled' => $this->decision->plc_gate_enabled,
                    'min_paying_customers' => $this->decision->min_paying_customers,
                    'min_mrr' => $this->decision->min_mrr,
                    'actual_paying_customers' => null,
                    'actual_mrr' => null,
                    'decision_text_am' => $this->decision->decision_text_am,
                    'expansion_text_am' => $this->decision->expansion_text_am,
                    'decisions_list' => $this->decision->decisions_list,
                ]);
            }

            return $clonedReport->load(['reportMembers.activities', 'decision']);
        });
    }
}
