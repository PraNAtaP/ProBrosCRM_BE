<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Commission;
use App\Models\Deal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Base query - Sales sees own data, Admin sees all
        $dealQuery = Deal::query();
        $commissionQuery = Commission::query();
        
        if ($user->isSales()) {
            $dealQuery->where('user_id', $user->id);
            $commissionQuery->whereHas('deal', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        // Deals per stage
        $dealsPerStage = [];
        foreach (Deal::STATUSES as $status) {
            $countQuery = clone $dealQuery;
            $dealsPerStage[$status] = [
                'count' => $countQuery->where('status', $status)->count(),
                'label' => ucwords(str_replace('_', ' ', $status)),
                'color' => Deal::STATUS_COLORS[$status],
            ];
        }

        // Total value of revenue-generating deals (trial_order + active_customer + retained_growing)
        $wonQuery = clone $dealQuery;
        $totalWonValue = $wonQuery->whereIn('status', Deal::REVENUE_STATUSES)->sum('value');

        // Monthly commission (still useful for reference)
        $monthlyCommission = (clone $commissionQuery)
            ->whereMonth('calculation_date', $currentMonth)
            ->whereYear('calculation_date', $currentYear)
            ->sum('amount');

        // Commission split by status (from commissions table)
        $totalPaidCommission = (clone $commissionQuery)
            ->where('status', Commission::STATUS_PAID)
            ->sum('amount');

        $totalPendingCommission = (clone $commissionQuery)
            ->where('status', Commission::STATUS_PENDING)
            ->sum('amount');

        // Total sales revenue from all revenue-generating statuses
        $salesRevenueQuery = clone $dealQuery;
        $totalSalesRevenue = $salesRevenueQuery
            ->whereIn('status', Deal::REVENUE_STATUSES)
            ->sum('value');

        // Recent activities
        $activityQuery = ActivityLog::with(['deal', 'user']);
        if ($user->isSales()) {
            $activityQuery->whereHas('deal', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }
        $recentActivities = $activityQuery
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'type' => $activity->activity_type,
                    'notes' => $activity->notes,
                    'deal_title' => $activity->deal->title ?? null,
                    'user_name' => $activity->user->name ?? null,
                    'created_at' => $activity->created_at->toIso8601String(),
                ];
            });

        // Total active deals
        $totalActiveDeals = (clone $dealQuery)->whereNotIn('status', [
            Deal::STATUS_LOST_CUSTOMER,
        ])->count();

        return response()->json([
            'deals_per_stage' => $dealsPerStage,
            'total_won_value' => (float) $totalWonValue,
            'monthly_commission' => (float) $monthlyCommission,
            'total_paid_commission' => (float) $totalPaidCommission,
            'total_pending_commission' => (float) $totalPendingCommission,
            'total_sales_revenue' => (float) $totalSalesRevenue,
            'total_active_deals' => $totalActiveDeals,
            'recent_activities' => $recentActivities,
            'month' => $currentMonth,
            'year' => $currentYear,
        ]);
    }
}
