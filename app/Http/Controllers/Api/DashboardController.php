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
            $isSales = $user->isSales();
            $currentMonth = now()->month;
            $currentYear = now()->year;

            // Deal aggregates — single GROUP BY query
            $dealBase = Deal::query();
            if ($isSales) {
                $dealBase->where('user_id', $user->id);
            }

            $stageStats = (clone $dealBase)
                ->select('status', DB::raw('COUNT(*) as cnt'), DB::raw('COALESCE(SUM(value), 0) as total'))
                ->groupBy('status')
                ->get()
                ->keyBy('status');

            $dealsPerStage = [];
            foreach (Deal::STATUSES as $status) {
                $row = $stageStats->get($status);
                $dealsPerStage[$status] = [
                    'count' => (int) ($row?->cnt ?? 0),
                    'label' => ucwords(str_replace('_', ' ', $status)),
                    'color' => Deal::STATUS_COLORS[$status] ?? '#64748b',
                ];
            }

            $totalWonValue = collect(Deal::REVENUE_STATUSES)
                ->sum(fn($s) => (float) ($stageStats->get($s)?->total ?? 0));

            $totalActiveDeals = collect(Deal::STATUSES)
                ->reject(fn($s) => $s === Deal::STATUS_LOST_CUSTOMER)
                ->sum(fn($s) => (int) ($stageStats->get($s)?->cnt ?? 0));

            unset($stageStats);

            // Commission aggregates
            $commBase = Commission::query();
            if ($isSales) {
                $commBase->whereHas('deal', fn($q) => $q->where('user_id', $user->id));
            }

            $monthlyCommission = (float) ((clone $commBase)
                ->whereMonth('calculation_date', $currentMonth)
                ->whereYear('calculation_date', $currentYear)
                ->sum('amount') ?? 0);

            $commSums = (clone $commBase)
                ->select('status', DB::raw('COALESCE(SUM(amount), 0) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');

            $totalPaidCommission = (float) ($commSums[Commission::STATUS_PAID] ?? 0);
            $totalPendingCommission = (float) ($commSums[Commission::STATUS_PENDING] ?? 0);

            unset($commSums, $commBase);

            // Recent activities — null-safe mapping
            $activityQuery = ActivityLog::select(['id', 'deal_id', 'user_id', 'activity_type', 'notes', 'created_at'])
                ->with([
                    'deal' => fn($q) => $q->select(['id', 'title']),
                    'user' => fn($q) => $q->select(['id', 'name']),
                ]);
            if ($isSales) {
                $activityQuery->where('user_id', $user->id);
            }

            $recentActivities = $activityQuery
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(fn($a) => [
                    'id' => $a->id,
                    'type' => $a->activity_type,
                    'notes' => $a->notes ?? '',
                    'deal_title' => $a->deal?->title ?? null,
                    'user_name' => $a->user?->name ?? null,
                    'created_at' => $a->created_at?->toIso8601String() ?? now()->toIso8601String(),
                ]);

            return response()->json([
                'deals_per_stage' => $dealsPerStage,
                'total_won_value' => (float) $totalWonValue,
                'monthly_commission' => $monthlyCommission,
                'total_paid_commission' => $totalPaidCommission,
                'total_pending_commission' => $totalPendingCommission,
                'total_sales_revenue' => (float) $totalWonValue,
                'total_active_deals' => (int) $totalActiveDeals,
                'recent_activities' => $recentActivities,
                'month' => $currentMonth,
                'year' => $currentYear,
            ]);
        } catch (\Throwable $e) {
            Log::error('DashboardController@index failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to load dashboard data.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }
}
