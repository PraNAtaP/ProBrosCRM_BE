<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Commission;
use App\Models\Deal;
use Illuminate\Support\Facades\Log;

class DealObserver
{
    public function created(Deal $deal): void
    {
        $this->logActivity($deal, ActivityLog::TYPE_NOTE, "Deal created with status: {$deal->status}");
    }

    public function updated(Deal $deal): void
    {
        if ($deal->isDirty('status')) {
            $oldStatus = $deal->getOriginal('status');
            $newStatus = $deal->status;

            $this->logActivity(
                $deal,
                ActivityLog::TYPE_STATUS_CHANGE,
                "Status changed from '{$oldStatus}' to '{$newStatus}'"
            );

            if (in_array($newStatus, Deal::REVENUE_STATUSES)) {
                $this->createCommission($deal);
            }
        }

        if ($deal->isDirty('value') && in_array($deal->status, Deal::REVENUE_STATUSES)) {
            try {
                $commission = $deal->commission;
                if ($commission && $commission->status === Commission::STATUS_PENDING) {
                    $commission->update([
                        'amount' => Commission::calculateAmount((float) $deal->value),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('DealObserver: failed to recalculate commission', [
                    'deal_id' => $deal->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function deleted(Deal $deal): void
    {
        $this->logActivity($deal, ActivityLog::TYPE_NOTE, "Deal deleted");
    }

    public function restored(Deal $deal): void
    {
        $this->logActivity($deal, ActivityLog::TYPE_NOTE, "Deal restored");
    }

    protected function createCommission(Deal $deal): void
    {
        try {
            if (!$deal->commission()->exists()) {
                Commission::create([
                    'deal_id' => $deal->id,
                    'amount' => Commission::calculateAmount((float) $deal->value),
                    'calculation_date' => now()->toDateString(),
                    'status' => Commission::STATUS_PENDING,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('DealObserver: failed to create commission', [
                'deal_id' => $deal->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function logActivity(Deal $deal, string $type, string $notes): void
    {
        try {
            ActivityLog::create([
                'deal_id' => $deal->id,
                'user_id' => auth()->id() ?? $deal->user_id,
                'activity_type' => $type,
                'notes' => $notes,
            ]);
        } catch (\Throwable $e) {
            Log::error('DealObserver: failed to log activity', [
                'deal_id' => $deal->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
