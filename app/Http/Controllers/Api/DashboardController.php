<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Commission;
use App\Models\Deal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currentMonth = now()->month;
            $currentYear = now()->year;
            $isSales = $user->isSales();

            // RAM: use raw aggregation instead of loading models
            $dealBase = Deal::query();
            if ($isSales) {
                $dealBase->where('user_id', $user->id);
            }

            // Single query: counts + sums per status
            $stageStats = (clone $dealBase)
                ->select('status', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(value) as total'))
                ->groupBy('status')
                ->pluck(null, 'status');

            $dealsPerStage = [];
            foreach (Deal::STATUSES as $status) {
                $row = $stageStats[$status] ?? null;
                $dealsPerStage[$status] = [
                    'count' => (int) ($row->cnt ?? 0),
                    'label' => ucwords(str_replace('_', ' ', $status)),
                    'color' => Deal::STATUS_COLORS[$status],
                ];
            }

            $totalWonValue = collect(Deal::REVENUE_STATUSES)
                ->sum(fn($s) => (float) ($stageStats[$s]->total ?? 0));

            $totalActiveDeals = collect(Deal::STATUSES)
                ->reject(fn($s) => $s === Deal::STATUS_LOST_CUSTOMER)
                ->sum(fn($s) => (int) ($stageStats[$s]->cnt ?? 0));

            unset($stageStats); // free RAM

            // Commission aggregates — single query
            $commBase = Commission::query();
            if ($isSales) {
                $commBase->whereHas('deal', fn($q) => $q->where('user_id', $user->id));
            }

            $monthlyCommission = (clone $commBase)
                ->whereMonth('calculation_date', $currentMonth)
                ->whereYear('calculation_date', $currentYear)
                ->sum('amount');

            $commSums = (clone $commBase)
                ->select('status', DB::raw('SUM(amount) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');

            $totalPaidCommission = (float) ($commSums[Commission::STATUS_PAID] ?? 0);
            $totalPendingCommission = (float) ($commSums[Commission::STATUS_PENDING] ?? 0);

            unset($commSums, $commBase); // free RAM

            $totalSalesRevenue = (float) $totalWonValue;

            // Recent activities — lean select, limit 10
            $activityQuery = ActivityLog::select(['id', 'deal_id', 'user_id', 'activity_type', 'notes', 'created_at'])
                ->with([
                    'deal' => fn($q) => $q->select(['id', 'title']),
                    'user' => fn($q) => $q->select(['id', 'name']),
                ]);
            if ($isSales) {
                $activityQuery->whereHas('deal', fn($q) => $q->where('user_id', $user->id));
            }

            $recentActivities = $activityQuery
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(fn($a) => [
                    'id' => $a->id,
                    'type' => $a->activity_type,
                    'notes' => $a->notes,
                    'deal_title' => $a->deal->title ?? null,
                    'user_name' => $a->user->name ?? null,
                    'created_at' => $a->created_at->toIso8601String(),
                ]);

            $response = response()->json([
                'deals_per_stage' => $dealsPerStage,
                'total_won_value' => (float) $totalWonValue,
                'monthly_commission' => (float) $monthlyCommission,
                'total_paid_commission' => $totalPaidCommission,
                'total_pending_commission' => $totalPendingCommission,
                'total_sales_revenue' => $totalSalesRevenue,
                'total_active_deals' => $totalActiveDeals,
                'recent_activities' => $recentActivities,
                'month' => $currentMonth,
                'year' => $currentYear,
            ]);

            // Free RAM before response is sent
            unset($dealsPerStage, $recentActivities);

            return $response;
        } catch (\Throwable $e) {
            Log::error('DashboardController@index failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to load dashboard data.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }
}
