<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'expense_category_id',
        'expense_date',
        'expense_type',
        'category',
        'vendor',
        'description',
        'amount_halalas',
        'vat_halalas',
        'payment_method',
        'payment_reference',
        'status',
        'receipt_path',
        'customer_id',
        'contract_id',
        'visit_id',
    ];

    protected function casts(): array
    {
        return [
            'amount_halalas' => 'integer',
            'vat_halalas' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function categoryModel(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
