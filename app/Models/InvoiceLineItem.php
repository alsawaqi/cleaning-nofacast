<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLineItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'visit_id',
        'line_type',
        'description',
        'quantity',
        'unit_label',
        'unit_price_halalas',
        'vat_rate',
        'net_total_halalas',
        'vat_total_halalas',
        'gross_total_halalas',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_halalas' => 'integer',
            'vat_rate' => 'integer',
            'net_total_halalas' => 'integer',
            'vat_total_halalas' => 'integer',
            'gross_total_halalas' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
