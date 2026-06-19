<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostItem extends Model
{
    protected $fillable = [
        'name',
        'item_type',
        'unit',
        'purchase_cost_halalas',
        'estimated_life_months',
        'estimated_monthly_uses',
        'default_cost_per_use_halalas',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'purchase_cost_halalas' => 'integer',
            'estimated_life_months' => 'integer',
            'estimated_monthly_uses' => 'integer',
            'default_cost_per_use_halalas' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function serviceCostItems(): HasMany
    {
        return $this->hasMany(ServiceCostItem::class);
    }
}
