<?php

namespace App\Models;

use Database\Factories\ServicePricingRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePricingRule extends Model
{
    /** @use HasFactory<ServicePricingRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'service_id',
        'name',
        'pricing_type',
        'unit_label',
        'unit_price_halalas',
        'minimum_quantity',
        'maximum_quantity',
        'vat_rate',
        'prices_include_vat',
        'applies_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_halalas' => 'integer',
            'minimum_quantity' => 'integer',
            'maximum_quantity' => 'integer',
            'vat_rate' => 'integer',
            'prices_include_vat' => 'boolean',
            'applies_to' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
