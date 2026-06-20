<?php

namespace App\Models;

use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'customer_site_id',
        'service_id',
        'service_package_id',
        'reference',
        'status',
        'starts_on',
        'ends_on',
        'monthly_fee_halalas',
        'vat_rate',
        'prices_include_vat',
        'pricing_model',
        'agreed_workers',
        'visits_per_week',
        'hours_per_visit',
        'planned_weekly_minutes',
        'included_materials',
        'material_policy',
        'estimated_material_cost_halalas',
        'extra_hour_rate_halalas',
        'overtime_policy',
        'service_scope',
        'terms_and_conditions',
        'sla_kpi_template',
        'payment_plan',
        'billing_cycle',
        'notice_days',
        'auto_renews',
        'special_terms',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'monthly_fee_halalas' => 'integer',
            'vat_rate' => 'integer',
            'prices_include_vat' => 'boolean',
            'agreed_workers' => 'integer',
            'visits_per_week' => 'integer',
            'hours_per_visit' => 'decimal:2',
            'planned_weekly_minutes' => 'integer',
            'included_materials' => 'boolean',
            'estimated_material_cost_halalas' => 'integer',
            'extra_hour_rate_halalas' => 'integer',
            'service_scope' => 'array',
            'sla_kpi_template' => 'array',
            'payment_plan' => 'array',
            'billing_cycle' => 'string',
            'notice_days' => 'integer',
            'auto_renews' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(CustomerSite::class, 'customer_site_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function servicePackage(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class);
    }

    public function addendums(): HasMany
    {
        return $this->hasMany(ContractAddendum::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function contractDecisions(): HasMany
    {
        return $this->hasMany(ContractDecision::class);
    }

    public function visitFeedback(): HasMany
    {
        return $this->hasMany(VisitFeedback::class);
    }
}
