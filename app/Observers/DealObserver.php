<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Commission;
use App\Models\Deal;

class DealObserver
{
    /**
     * Handle the Deal "created" event.
     */
    public function created(Deal $deal): void
    {
        // Log deal creation
        $this->logActivity($deal, ActivityLog::TYPE_NOTE, "Deal created with status: {$deal->status}");
    }

    /**
     * Handle the Deal "updated" event.
     */
    public function updated(Deal $deal): void
    {
        // Check if status changed
        if ($deal->isDirty('status')) {
            $oldStatus = $deal->getOriginal('status');
            $newStatus = $deal->status;

            // Log the status change
            $this->logActivity(
                $deal,
                ActivityLog::TYPE_STATUS_CHANGE,
                "Status changed from '{$oldStatus}' to '{$newStatus}'"
            );

            // Auto-create commission when deal enters a revenue-generating status
            // (trial_order, active_customer, retained_growing)
            // The createCommission() method has a guard to prevent duplicates,
            // so moving between these statuses won't create a second commission.
            if (in_array($newStatus, Deal::REVENUE_STATUSES)) {
                $this->createCommission($deal);
            }
        }

        // Recalculate commission when deal value changes
        if ($deal->isDirty('value') && in_array($deal->status, Deal::REVENUE_STATUSES)) {
            $commission = $deal->commission;
            if ($commission && $commission->status === Commission::STATUS_PENDING) {
                $commission->update([
                    'amount' => Commission::calculateAmount((float) $deal->value),
                ]);
            }
        }
    }

    /**
     * Handle the Deal "deleted" event.
     */
    public function deleted(Deal $deal): void
    {
        $this->logActivity($deal, ActivityLog::TYPE_NOTE, "Deal deleted");
    }

    /**
     * Handle the Deal "restored" event.
     */
    public function restored(Deal $deal): void
    {
        $this->logActivity($deal, ActivityLog::TYPE_NOTE, "Deal restored");
    }

    /**
     * Create a commission for the deal.
     */
    protected function createCommission(Deal $deal): void
    {
        // Only create if commission doesn't already exist
        if (!$deal->commission()->exists()) {
            Commission::create([
                'deal_id' => $deal->id,
                'amount' => Commission::calculateAmount((float) $deal->value),
                'calculation_date' => now()->toDateString(),
                'status' => Commission::STATUS_PENDING,
            ]);
        }
    }

    /**
     * Log an activity for the deal.
     */
    protected function logActivity(Deal $deal, string $type, string $notes): void
    {
        ActivityLog::create([
            'deal_id' => $deal->id,
            'user_id' => auth()->id() ?? $deal->user_id,
            'activity_type' => $type,
            'notes' => $notes,
        ]);
    }
}
