<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractAddendum extends Model
{
    protected $fillable = [
        'contract_id',
        'number',
        'title',
        'summary',
        'effective_on',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'effective_on' => 'date',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
