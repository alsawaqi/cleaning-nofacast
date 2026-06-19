<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCostItem extends Model
{
    protected $fillable = [
        'service_id',
        'cost_item_id',
        'quantity',
        'cost_per_use_halalas',
        'line_total_halalas',
        'charge_customer',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'cost_per_use_halalas' => 'integer',
            'line_total_halalas' => 'integer',
            'charge_customer' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function costItem(): BelongsTo
    {
        return $this->belongsTo(CostItem::class);
    }
}
