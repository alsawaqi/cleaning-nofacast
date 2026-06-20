<?php

namespace App\Models;

use Database\Factories\CustomerSiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerSite extends Model
{
    /** @use HasFactory<CustomerSiteFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'country_code',
        'name',
        'city',
        'district',
        'address',
        'latitude',
        'longitude',
        'google_place_id',
        'formatted_address',
        'is_default',
        'contact_name',
        'contact_phone',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_default' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
