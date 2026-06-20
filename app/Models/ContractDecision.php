<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractDecision extends Model
{
    protected $fillable = [
        'customer_id',
        'contract_id',
        'decision',
        'status',
        'signer_name',
        'signer_title',
        'signature_text',
        'customer_note',
        'admin_note',
        'reviewed_by',
        'accepted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
