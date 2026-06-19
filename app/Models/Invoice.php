<?php

namespace App\Models;

use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'customer_id',
        'customer_site_id',
        'number',
        'status',
        'issue_date',
        'due_date',
        'net_total_halalas',
        'vat_total_halalas',
        'gross_total_halalas',
        'paid_total_halalas',
        'vat_rate',
        'zatca_qr',
        'provider_payload',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'net_total_halalas' => 'integer',
            'vat_total_halalas' => 'integer',
            'gross_total_halalas' => 'integer',
            'paid_total_halalas' => 'integer',
            'vat_rate' => 'integer',
            'provider_payload' => 'array',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function cheques(): HasMany
    {
        return $this->hasMany(Cheque::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function getCreditTotalHalalasAttribute(): int
    {
        if ($this->relationLoaded('creditNotes')) {
            return (int) $this->creditNotes
                ->where('status', 'approved')
                ->sum('amount_halalas');
        }

        return (int) $this->creditNotes()
            ->where('status', 'approved')
            ->sum('amount_halalas');
    }

    public function getBalanceHalalasAttribute(): int
    {
        return max(0, $this->gross_total_halalas - $this->paid_total_halalas - $this->credit_total_halalas);
    }
}
