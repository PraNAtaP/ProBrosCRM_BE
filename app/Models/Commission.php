<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    use HasFactory;

    /**
     * Commission status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';

    /**
     * Commission rate (0.5% of Sales Revenue)
     */
    public const RATE = 0.005;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'deal_id',
        'amount',
        'calculation_date',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'calculation_date' => 'date',
    ];

    /**
     * Get the deal that generated the commission.
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * Calculate commission amount for a deal value.
     */
    public static function calculateAmount(float $dealValue): float
    {
        return $dealValue * self::RATE;
    }
}
