<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportDecision extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'report_id',
        'plc_gate_enabled',
        'min_paying_customers',
        'min_mrr',
        'actual_paying_customers',
        'actual_mrr',
        'decision_text_am',
        'expansion_text_am',
        'decisions_list',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plc_gate_enabled' => 'boolean',
            'min_paying_customers' => 'integer',
            'min_mrr' => 'decimal:2',
            'actual_paying_customers' => 'integer',
            'actual_mrr' => 'decimal:2',
            'decisions_list' => 'array',
        ];
    }

    /**
     * Get the report this decision belongs to.
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
