<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Commission extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';

    public const RATE = 0.005;

    protected $fillable = [
        'deal_id',
        'amount',
        'calculation_date',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'calculation_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public static function calculateAmount(float $dealValue): float
    {
        return $dealValue * self::RATE;
    }
}
