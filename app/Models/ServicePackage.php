<?php

namespace App\Models;

use Database\Factories\ServicePackageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePackage extends Model
{
    /** @use HasFactory<ServicePackageFactory> */
    use HasFactory;

    protected $fillable = [
        'service_id',
        'name',
        'description',
        'billing_cycle',
        'visit_frequency',
        'visits_per_week',
        'hours_per_visit',
        'worker_count',
        'duration_minutes',
        'expected_labor_minutes',
        'material_cost_halalas',
        'price_halalas',
        'vat_rate',
        'prices_include_vat',
        'checklist_template',
        'sla_kpi_template',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'worker_count' => 'integer',
            'visits_per_week' => 'integer',
            'hours_per_visit' => 'decimal:2',
            'duration_minutes' => 'integer',
            'expected_labor_minutes' => 'integer',
            'material_cost_halalas' => 'integer',
            'price_halalas' => 'integer',
            'vat_rate' => 'integer',
            'prices_include_vat' => 'boolean',
            'checklist_template' => 'array',
            'sla_kpi_template' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
