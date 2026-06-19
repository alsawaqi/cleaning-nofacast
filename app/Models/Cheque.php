<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cheque extends Model
{
    protected $fillable = [
        'customer_id',
        'invoice_id',
        'cheque_number',
        'amount_halalas',
        'due_date',
        'cleared_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount_halalas' => 'integer',
            'due_date' => 'date',
            'cleared_date' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
