<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingRecord extends Model
{
    protected $fillable = [
        'worker_id',
        'course_name',
        'certificate_code',
        'completed_on',
        'expires_on',
    ];

    protected function casts(): array
    {
        return [
            'completed_on' => 'date',
            'expires_on' => 'date',
        ];
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }
}
