<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;

    public const TYPE_CALL = 'call';
    public const TYPE_EMAIL = 'email';
    public const TYPE_MEETING = 'meeting';
    public const TYPE_STATUS_CHANGE = 'status_change';
    public const TYPE_NOTE = 'note';

    public const TYPES = [
        self::TYPE_CALL,
        self::TYPE_EMAIL,
        self::TYPE_MEETING,
        self::TYPE_STATUS_CHANGE,
        self::TYPE_NOTE,
    ];

    public const MANUAL_TYPES = [
        self::TYPE_CALL,
        self::TYPE_MEETING,
        self::TYPE_EMAIL,
    ];

    public const MEETING_TYPE_ONLINE = 'Online';
    public const MEETING_TYPE_OFFLINE = 'Offline';

    public const MEETING_TYPES = [
        self::MEETING_TYPE_ONLINE,
        self::MEETING_TYPE_OFFLINE,
    ];

    protected $fillable = [
        'deal_id',
        'contact_id',
        'company_id',
        'user_id',
        'activity_type',
        'meeting_type',
        'start_time',
        'duration',
        'notes',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'duration' => 'integer',
    ];

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
