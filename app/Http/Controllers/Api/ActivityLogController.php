<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\Deal;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    public function index(Request $request, Deal $deal): AnonymousResourceCollection|JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->isSales() && $deal->user_id !== $user->id) {
                return response()->json([
                    'message' => 'Unauthorized to view activities for this deal',
                ], 403);
            }

            $activities = $deal->activityLogs()
                ->with(['user' => fn($q) => $q->select(['id', 'name'])])
                ->orderBy('created_at', 'desc')
                ->get();

            return ActivityLogResource::collection($activities);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to load activities.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function store(Request $request, Deal $deal): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->isSales() && $deal->user_id !== $user->id) {
                return response()->json([
                    'message' => 'Unauthorized to add activities to this deal',
                ], 403);
            }

            $validated = $request->validate([
                'activity_type' => 'required|in:' . implode(',', ActivityLog::TYPES),
                'notes' => 'nullable|string',
            ]);

            $activity = $deal->activityLogs()->create([
                'user_id' => $user->id,
                'activity_type' => $validated['activity_type'],
                'notes' => $validated['notes'] ?? null,
            ]);

            return response()->json([
                'message' => 'Activity logged successfully',
                'data' => new ActivityLogResource($activity->load('user')),
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to log activity.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function storeManual(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validated = $request->validate([
                'activity_type' => 'required|in:' . implode(',', ActivityLog::MANUAL_TYPES),
                'meeting_type' => 'nullable|required_if:activity_type,meeting|in:' . implode(',', ActivityLog::MEETING_TYPES),
                'start_time' => 'required|date',
                'duration' => 'required|integer|min:1',
                'notes' => 'required|string|max:5000',
                'deal_id' => 'nullable|exists:deals,id',
                'contact_id' => 'nullable|exists:contacts,id',
                'company_id' => 'nullable|exists:companies,id',
            ]);

            $companyId = $validated['company_id'] ?? null;
            $contactId = $validated['contact_id'] ?? null;
            if ($contactId && !$companyId) {
                $contact = Contact::select(['id', 'company_id'])->find($contactId);
                if ($contact) {
                    $companyId = $contact->company_id;
                }
            }

            if ($validated['deal_id'] && $user->isSales()) {
                $deal = Deal::select(['id', 'user_id'])->find($validated['deal_id']);
                if ($deal && $deal->user_id !== $user->id) {
                    return response()->json([
                        'message' => 'Unauthorized to log activities for this deal',
                    ], 403);
                }
            }

            $activity = ActivityLog::create([
                'user_id' => $user->id,
                'deal_id' => $validated['deal_id'] ?? null,
                'contact_id' => $contactId,
                'company_id' => $companyId,
                'activity_type' => $validated['activity_type'],
                'meeting_type' => $validated['activity_type'] === 'meeting' ? ($validated['meeting_type'] ?? null) : null,
                'start_time' => $validated['start_time'],
                'duration' => $validated['duration'],
                'notes' => $validated['notes'],
            ]);

            return response()->json([
                'message' => 'Activity logged successfully',
                'data' => new ActivityLogResource($activity->load(['user', 'deal', 'contact', 'company'])),
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to log activity.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function reports(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $period = $request->input('period', 'monthly');

            $now = Carbon::now();
            switch ($period) {
                case 'daily':
                    $startDate = $now->copy()->startOfDay();
                    $endDate = $now->copy()->endOfDay();
                    $trendStart = $now->copy()->subDays(29)->startOfDay();
                    $groupFormat = '%Y-%m-%d';
                    $carbonFormat = 'Y-m-d';
                    break;
                case 'weekly':
                    $startDate = $now->copy()->startOfWeek();
                    $endDate = $now->copy()->endOfWeek();
                    $trendStart = $now->copy()->subWeeks(11)->startOfWeek();
                    $groupFormat = '%x-W%v';
                    $carbonFormat = 'o-\\WW';
                    break;
                default:
                    $startDate = $now->copy()->startOfMonth();
                    $endDate = $now->copy()->endOfMonth();
                    $trendStart = $now->copy()->subMonths(11)->startOfMonth();
                    $groupFormat = '%Y-%m';
                    $carbonFormat = 'Y-m';
                    break;
            }

            $activityQuery = ActivityLog::query();
            $dealQuery = Deal::query();

            if ($user->isSales()) {
                $activityQuery->where('user_id', $user->id);
                $dealQuery->where('user_id', $user->id);
            }

            $periodActivities = (clone $activityQuery)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('activity_type', ActivityLog::MANUAL_TYPES);

            $totalCalls = (clone $periodActivities)->where('activity_type', 'call')->count();
            $totalMeetings = (clone $periodActivities)->where('activity_type', 'meeting')->count();
            $totalEmails = (clone $periodActivities)->where('activity_type', 'email')->count();

            $newActiveCustomers = (clone $dealQuery)
                ->where('status', Deal::STATUS_ACTIVE_CUSTOMER)
                ->whereBetween('updated_at', [$startDate, $endDate])
                ->count();

            $revenueQuery = (clone $dealQuery)->whereIn('status', Deal::REVENUE_STATUSES);
            $totalRevenue = (clone $revenueQuery)->sum('value');
            $totalProfit = round((float) $totalRevenue * 0.15, 2);

            $trendData = (clone $activityQuery)
                ->whereIn('activity_type', ActivityLog::MANUAL_TYPES)
                ->where('created_at', '>=', $trendStart)
                ->select(
                    DB::raw("DATE_FORMAT(created_at, '{$groupFormat}') as period_label"),
                    DB::raw("SUM(CASE WHEN activity_type = 'call' THEN 1 ELSE 0 END) as calls"),
                    DB::raw("SUM(CASE WHEN activity_type = 'meeting' THEN 1 ELSE 0 END) as meetings"),
                    DB::raw("SUM(CASE WHEN activity_type = 'email' THEN 1 ELSE 0 END) as emails"),
                    DB::raw("COUNT(*) as total")
                )
                ->groupBy('period_label')
                ->orderBy('period_label')
                ->get();

            if ($trendData->isEmpty()) {
                $sqliteGroupFormat = match ($period) {
                    'daily' => '%Y-%m-%d',
                    'weekly' => '%Y-W%W',
                    default => '%Y-%m',
                };

                $trendData = (clone $activityQuery)
                    ->whereIn('activity_type', ActivityLog::MANUAL_TYPES)
                    ->where('created_at', '>=', $trendStart)
                    ->select(
                        DB::raw("strftime('{$sqliteGroupFormat}', created_at) as period_label"),
                        DB::raw("SUM(CASE WHEN activity_type = 'call' THEN 1 ELSE 0 END) as calls"),
                        DB::raw("SUM(CASE WHEN activity_type = 'meeting' THEN 1 ELSE 0 END) as meetings"),
                        DB::raw("SUM(CASE WHEN activity_type = 'email' THEN 1 ELSE 0 END) as emails"),
                        DB::raw("COUNT(*) as total")
                    )
                    ->groupBy('period_label')
                    ->orderBy('period_label')
                    ->get();
            }

            $recentActivities = (clone $activityQuery)
                ->with([
                    'user' => fn($q) => $q->select(['id', 'name']),
                    'deal' => fn($q) => $q->select(['id', 'title']),
                    'contact' => fn($q) => $q->select(['id', 'name', 'email', 'phone']),
                    'company' => fn($q) => $q->select(['id', 'name', 'address']),
                ])
                ->whereIn('activity_type', ActivityLog::MANUAL_TYPES)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            return response()->json([
                'period' => $period,
                'start_date' => $startDate->toIso8601String(),
                'end_date' => $endDate->toIso8601String(),
                'stats' => [
                    'total_calls' => $totalCalls,
                    'total_meetings' => $totalMeetings,
                    'total_emails' => $totalEmails,
                    'new_active_customers' => $newActiveCustomers,
                    'total_revenue' => (float) $totalRevenue,
                    'total_profit' => $totalProfit,
                ],
                'trend' => $trendData,
                'recent_activities' => ActivityLogResource::collection($recentActivities),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to load reports.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }
}
