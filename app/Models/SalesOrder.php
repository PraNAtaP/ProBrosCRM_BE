<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
    ];

    public const STATUS_COLORS = [
        self::STATUS_PENDING => '#f59e0b',
        self::STATUS_PROCESSING => '#3b82f6',
        self::STATUS_DELIVERED => '#10b981',
        self::STATUS_CANCELLED => '#ef4444',
    ];

    protected $fillable = [
        'company_id',
        'contact_id',
        'user_id',
        'order_number',
        'status',
        'total_amount',
        'is_favorite',
        'is_second_run',
        'needs_price_sync',
        'additional_notes',
        'delivery_date',
        'delivered_at',
        'cancelled_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'is_favorite' => 'boolean',
        'is_second_run' => 'boolean',
        'needs_price_sync' => 'boolean',
        'delivery_date' => 'date',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate next order number: SO-2026-0001
     */
    public static function generateOrderNumber(): string
    {
        $year = now()->year;
        $lastOrder = static::withTrashed()
            ->where('order_number', 'like', "SO-{$year}-%")
            ->orderBy('order_number', 'desc')
            ->first();

        if ($lastOrder) {
            $lastNum = (int) substr($lastOrder->order_number, -4);
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return sprintf('SO-%d-%04d', $year, $nextNum);
    }
}
