<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequest extends Model
{
    protected $fillable = [
        'type',
        'status',
        'priority',
        'customer_id',
        'contract_id',
        'visit_id',
        'subject',
        'description',
        'requested_for',
        'starts_at',
        'ends_at',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_for' => 'date',
            'reviewed_at' => 'datetime',
            'resolved_at' => 'datetime',
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

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
