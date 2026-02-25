<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'area_id',
        'name',
        'trading_name',
        'address',
        'industry',
        'phone',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Display name: trading_name if available, otherwise name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->trading_name ?: $this->name;
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * Legacy one-to-many (backward compatibility via company_id FK).
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * Many-to-many: a company can have multiple contacts.
     */
    public function allContacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_company')
            ->withTimestamps();
    }

    public function deals(): HasManyThrough
    {
        return $this->hasManyThrough(Deal::class, Contact::class);
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }
}
