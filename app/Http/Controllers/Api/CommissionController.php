<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommissionResource;
use App\Models\Commission;
use App\Models\Deal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommissionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        try {
            $user = $request->user();
            $query = Commission::with([
                'deal' => fn($q) => $q->select(['id', 'title', 'value', 'contact_id', 'user_id']),
                'deal.user' => fn($q) => $q->select(['id', 'name']),
                'deal.contact' => fn($q) => $q->select(['id', 'name', 'company_id']),
                'deal.contact.company' => fn($q) => $q->select(['id', 'name']),
            ]);

            if ($user->isSales()) {
                $query->whereHas('deal', fn($q) => $q->where('user_id', $user->id));
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('month') && $request->has('year')) {
                $query->whereMonth('calculation_date', $request->month)
                      ->whereYear('calculation_date', $request->year);
            }

            $query->orderBy('calculation_date', 'desc');

            $perPage = min((int) $request->input('per_page', 30), 100);
            return CommissionResource::collection($query->simplePaginate($perPage));
        } catch (\Throwable $e) {
            Log::error('CommissionController@index failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to load commissions.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function show(Request $request, Commission $commission): CommissionResource|JsonResponse
    {
        try {
            $user = $request->user();

            // Null-safe: load deal first, then check ownership
            $commission->load('deal');
            if ($user->isSales() && $commission->deal?->user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized to view this commission'], 403);
            }

            return new CommissionResource($commission->load('deal.user', 'deal.contact.company'));
        } catch (\Throwable $e) {
            Log::error('CommissionController@show failed', [
                'commission_id' => $commission->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to load commission.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function markAsPaid(Request $request, Commission $commission): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user->isAdmin()) {
                return response()->json(['message' => 'Only admin can mark commissions as paid'], 403);
            }

            DB::transaction(function () use ($commission) {
                $commission->update(['status' => Commission::STATUS_PAID]);
            });

            return response()->json([
                'message' => 'Commission marked as paid',
                'data' => new CommissionResource($commission->fresh()->load('deal.user')),
            ]);
        } catch (\Throwable $e) {
            Log::error('CommissionController@markAsPaid failed', [
                'commission_id' => $commission->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to update commission.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function summary(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currentMonth = now()->month;
            $currentYear = now()->year;

            $query = Commission::query();

            if ($user->isSales()) {
                $query->whereHas('deal', fn($q) => $q->where('user_id', $user->id));
            }

            $monthlyTotal = (clone $query)
                ->whereMonth('calculation_date', $currentMonth)
                ->whereYear('calculation_date', $currentYear)
                ->sum('amount');

            // Single query for status totals
            $statusSums = (clone $query)
                ->select('status', DB::raw('SUM(amount) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');

            return response()->json([
                'monthly_total' => (float) $monthlyTotal,
                'pending_total' => (float) ($statusSums[Commission::STATUS_PENDING] ?? 0),
                'paid_total' => (float) ($statusSums[Commission::STATUS_PAID] ?? 0),
                'month' => $currentMonth,
                'year' => $currentYear,
            ]);
        } catch (\Throwable $e) {
            Log::error('CommissionController@summary failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to load commission summary.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }
}
