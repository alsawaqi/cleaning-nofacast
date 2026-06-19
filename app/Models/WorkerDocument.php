<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerDocument extends Model
{
    protected $fillable = [
        'worker_id',
        'document_type',
        'document_number',
        'expires_on',
        'file_path',
    ];

    protected function casts(): array
    {
        return [
            'expires_on' => 'date',
        ];
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }
}
