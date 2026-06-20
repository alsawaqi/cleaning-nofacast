<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class WorkerAvailabilityBlock extends Model
{
    protected $fillable = [
        'worker_id',
        'date',
        'starts_at',
        'ends_at',
        'status',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function setDateAttribute(mixed $value): void
    {
        $this->attributes['date'] = Carbon::parse($value)->toDateString();
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
