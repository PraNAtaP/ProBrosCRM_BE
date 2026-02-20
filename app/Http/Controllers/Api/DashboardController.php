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
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currentMonth = now()->month;
            $currentYear = now()->year;

            $dealQuery = Deal::query();
            $commissionQuery = Commission::query();

            if ($user->isSales()) {
                $dealQuery->where('user_id', $user->id);
                $commissionQuery->whereHas('deal', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            }

            $dealsPerStage = [];
            foreach (Deal::STATUSES as $status) {
                $countQuery = clone $dealQuery;
                $dealsPerStage[$status] = [
                    'count' => $countQuery->where('status', $status)->count(),
                    'label' => ucwords(str_replace('_', ' ', $status)),
                    'color' => Deal::STATUS_COLORS[$status],
                ];
            }

            $wonQuery = clone $dealQuery;
            $totalWonValue = $wonQuery->whereIn('status', Deal::REVENUE_STATUSES)->sum('value');

            $monthlyCommission = (clone $commissionQuery)
                ->whereMonth('calculation_date', $currentMonth)
                ->whereYear('calculation_date', $currentYear)
                ->sum('amount');

            $totalPaidCommission = (clone $commissionQuery)
                ->where('status', Commission::STATUS_PAID)
                ->sum('amount');

            $totalPendingCommission = (clone $commissionQuery)
                ->where('status', Commission::STATUS_PENDING)
                ->sum('amount');

            $salesRevenueQuery = clone $dealQuery;
            $totalSalesRevenue = $salesRevenueQuery
                ->whereIn('status', Deal::REVENUE_STATUSES)
                ->sum('value');

            $activityQuery = ActivityLog::with([
                'deal' => fn($q) => $q->select(['id', 'title']),
                'user' => fn($q) => $q->select(['id', 'name']),
            ]);
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
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to load dashboard data.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }
}
