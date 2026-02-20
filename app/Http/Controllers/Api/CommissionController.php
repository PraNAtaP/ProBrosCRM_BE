<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommissionResource;
use App\Models\Commission;
use App\Models\Deal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
                $query->whereHas('deal', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('month') && $request->has('year')) {
                $query->whereMonth('calculation_date', $request->month)
                      ->whereYear('calculation_date', $request->year);
            }

            $commissions = $query->orderBy('calculation_date', 'desc')->get();
            return CommissionResource::collection($commissions);
        } catch (\Throwable $e) {
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

            if ($user->isSales() && $commission->deal->user_id !== $user->id) {
                return response()->json([
                    'message' => 'Unauthorized to view this commission',
                ], 403);
            }

            return new CommissionResource($commission->load('deal.user', 'deal.contact.company'));
        } catch (\Throwable $e) {
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
                return response()->json([
                    'message' => 'Only admin can mark commissions as paid',
                ], 403);
            }

            $commission->update(['status' => Commission::STATUS_PAID]);

            return response()->json([
                'message' => 'Commission marked as paid',
                'data' => new CommissionResource($commission->load('deal.user')),
            ]);
        } catch (\Throwable $e) {
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
                $query->whereHas('deal', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            }

            $monthlyQuery = clone $query;
            $monthlyTotal = $monthlyQuery
                ->whereMonth('calculation_date', $currentMonth)
                ->whereYear('calculation_date', $currentYear)
                ->sum('amount');

            $pendingQuery = clone $query;
            $pendingTotal = $pendingQuery->where('status', Commission::STATUS_PENDING)->sum('amount');

            $paidQuery = clone $query;
            $paidTotal = $paidQuery->where('status', Commission::STATUS_PAID)->sum('amount');

            return response()->json([
                'monthly_total' => (float) $monthlyTotal,
                'pending_total' => (float) $pendingTotal,
                'paid_total' => (float) $paidTotal,
                'month' => $currentMonth,
                'year' => $currentYear,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to load commission summary.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }
}
