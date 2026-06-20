<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Visit extends Model
{
    protected $fillable = [
        'contract_id',
        'assignment_id',
        'worker_id',
        'customer_site_id',
        'service_id',
        'scheduled_for',
        'starts_at',
        'ends_at',
        'status',
        'checked_in_at',
        'check_in_latitude',
        'check_in_longitude',
        'check_in_accuracy_meters',
        'checked_out_at',
        'check_out_latitude',
        'check_out_longitude',
        'check_out_accuracy_meters',
        'supervisor_acknowledged_at',
        'supervisor_acknowledged_by',
        'supervisor_note',
        'completion_review_status',
        'completion_reviewed_at',
        'completion_reviewed_by',
        'completion_review_note',
        'quality_status',
        'quality_score',
        'quality_reviewed_at',
        'quality_reviewed_by',
        'quality_notes',
        'quality_follow_up_required',
        'issue_note',
        'photos',
        'planned_minutes',
        'actual_minutes',
        'variance_minutes',
        'overtime_minutes',
        'billable_overtime_minutes',
        'overtime_status',
        'overtime_invoice_id',
        'overtime_billed_at',
        'materials_used',
        'planned_revenue_halalas',
        'labor_cost_halalas',
        'material_cost_halalas',
        'billable_overtime_halalas',
        'gross_profit_halalas',
        'execution_notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date',
            'checked_in_at' => 'datetime',
            'check_in_latitude' => 'decimal:7',
            'check_in_longitude' => 'decimal:7',
            'check_in_accuracy_meters' => 'integer',
            'checked_out_at' => 'datetime',
            'check_out_latitude' => 'decimal:7',
            'check_out_longitude' => 'decimal:7',
            'check_out_accuracy_meters' => 'integer',
            'supervisor_acknowledged_at' => 'datetime',
            'completion_reviewed_at' => 'datetime',
            'quality_score' => 'integer',
            'quality_reviewed_at' => 'datetime',
            'quality_follow_up_required' => 'boolean',
            'photos' => 'array',
            'planned_minutes' => 'integer',
            'actual_minutes' => 'integer',
            'variance_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'billable_overtime_minutes' => 'integer',
            'overtime_billed_at' => 'datetime',
            'materials_used' => 'array',
            'planned_revenue_halalas' => 'integer',
            'labor_cost_halalas' => 'integer',
            'material_cost_halalas' => 'integer',
            'billable_overtime_halalas' => 'integer',
            'gross_profit_halalas' => 'integer',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(CustomerSite::class, 'customer_site_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function overtimeInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'overtime_invoice_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_acknowledged_by');
    }

    public function completionReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completion_reviewed_by');
    }

    public function qualityReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quality_reviewed_by');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(ChecklistItem::class);
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(VisitFeedback::class);
    }
}
