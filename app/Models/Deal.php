<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deal extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Deal status lifecycle constants
     */
    public const STATUS_LEAD = 'lead';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_QUALIFIED = 'qualified';
    public const STATUS_QUOTES_SENT = 'quotes_sent';
    public const STATUS_TRIAL_ORDER = 'trial_order';
    public const STATUS_ACTIVE_CUSTOMER = 'active_customer';
    public const STATUS_RETAINED_GROWING = 'retained_growing';
    public const STATUS_LOST_CUSTOMER = 'lost_customer';

    /**
     * Statuses that count as revenue-generating (commission-eligible)
     */
    public const REVENUE_STATUSES = [
        self::STATUS_TRIAL_ORDER,
        self::STATUS_ACTIVE_CUSTOMER,
        self::STATUS_RETAINED_GROWING,
    ];

    /**
     * All possible statuses in order
     */
    public const STATUSES = [
        self::STATUS_LEAD,
        self::STATUS_CONTACTED,
        self::STATUS_QUALIFIED,
        self::STATUS_QUOTES_SENT,
        self::STATUS_TRIAL_ORDER,
        self::STATUS_ACTIVE_CUSTOMER,
        self::STATUS_RETAINED_GROWING,
        self::STATUS_LOST_CUSTOMER,
    ];

    /**
     * Status to color mapping for frontend
     */
    public const STATUS_COLORS = [
        self::STATUS_LEAD => '#3b82f6',           // blue
        self::STATUS_CONTACTED => '#8b5cf6',       // violet
        self::STATUS_QUALIFIED => '#6366f1',       // indigo
        self::STATUS_QUOTES_SENT => '#eab308',     // yellow
        self::STATUS_TRIAL_ORDER => '#f97316',     // orange
        self::STATUS_ACTIVE_CUSTOMER => '#10b981', // emerald
        self::STATUS_RETAINED_GROWING => '#22c55e', // green
        self::STATUS_LOST_CUSTOMER => '#ef4444',   // red
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'contact_id',
        'user_id',
        'title',
        'value',
        'status',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'value' => 'decimal:2',
    ];

    /**
     * Get the color for the current status.
     */
    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? '#64748b';
    }

    /**
     * Get the contact that owns the deal.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the user (sales rep) that owns the deal.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the commission for the deal.
     */
    public function commission(): HasOne
    {
        return $this->hasOne(Commission::class);
    }

    /**
     * Get the activity logs for the deal.
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}
