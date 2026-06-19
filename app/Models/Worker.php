<?php

namespace App\Models;

use Database\Factories\WorkerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Worker extends Model
{
    /** @use HasFactory<WorkerFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'employee_code',
        'name',
        'phone',
        'hired_on',
        'nationality',
        'role_language',
        'job_role',
        'status',
        'cost_rate_halalas',
        'skills',
        'certifications',
        'availability_notes',
    ];

    protected function casts(): array
    {
        return [
            'hired_on' => 'date',
            'cost_rate_halalas' => 'integer',
            'skills' => 'array',
            'certifications' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(WorkerDocument::class);
    }

    public function trainingRecords(): HasMany
    {
        return $this->hasMany(TrainingRecord::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(WorkerRevenueTarget::class);
    }
}
