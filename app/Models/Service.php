<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'category',
        'description',
        'pricing_type',
        'base_price_halalas',
        'vat_rate',
        'prices_include_vat',
        'materials_included',
        'minimum_billable_minutes',
        'default_workers',
        'default_duration_minutes',
        'default_material_cost_halalas',
        'material_policy',
        'included_materials',
        'extra_hour_rate_halalas',
        'overtime_policy',
        'allowed_frequencies',
        'required_certificates',
        'checklist_template',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price_halalas' => 'integer',
            'vat_rate' => 'integer',
            'prices_include_vat' => 'boolean',
            'materials_included' => 'boolean',
            'minimum_billable_minutes' => 'integer',
            'default_workers' => 'integer',
            'default_duration_minutes' => 'integer',
            'default_material_cost_halalas' => 'integer',
            'included_materials' => 'array',
            'extra_hour_rate_halalas' => 'integer',
            'allowed_frequencies' => 'array',
            'required_certificates' => 'array',
            'checklist_template' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(ServicePackage::class);
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(ServicePricingRule::class);
    }

    public function serviceCostItems(): HasMany
    {
        return $this->hasMany(ServiceCostItem::class);
    }
}
