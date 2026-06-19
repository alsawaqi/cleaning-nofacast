<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'checked_out_at',
        'supervisor_acknowledged_at',
        'supervisor_acknowledged_by',
        'supervisor_note',
        'issue_note',
        'photos',
        'planned_minutes',
        'actual_minutes',
        'variance_minutes',
        'overtime_minutes',
        'billable_overtime_minutes',
        'overtime_status',
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
            'checked_out_at' => 'datetime',
            'supervisor_acknowledged_at' => 'datetime',
            'photos' => 'array',
            'planned_minutes' => 'integer',
            'actual_minutes' => 'integer',
            'variance_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'billable_overtime_minutes' => 'integer',
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

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_acknowledged_by');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(ChecklistItem::class);
    }
}
