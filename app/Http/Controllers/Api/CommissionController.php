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
    /**
     * Display a listing of commissions.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $query = Commission::with('deal.user', 'deal.contact.company');

        // Sales can only see commissions for their own deals
        if ($user->isSales()) {
            $query->whereHas('deal', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by month
        if ($request->has('month') && $request->has('year')) {
            $query->whereMonth('calculation_date', $request->month)
                  ->whereYear('calculation_date', $request->year);
        }

        $commissions = $query->orderBy('calculation_date', 'desc')->get();
        return CommissionResource::collection($commissions);
    }

    /**
     * Display the specified commission.
     */
    public function show(Request $request, Commission $commission): CommissionResource|JsonResponse
    {
        $user = $request->user();

        // Sales can only view their own commissions
        if ($user->isSales() && $commission->deal->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized to view this commission',
            ], 403);
        }

        return new CommissionResource($commission->load('deal.user', 'deal.contact.company'));
    }

    /**
     * Mark a commission as paid (Admin only).
     */
    public function markAsPaid(Request $request, Commission $commission): JsonResponse
    {
        $user = $request->user();

        // Only admin can mark as paid
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
    }

    /**
     * Get commission summary for current user.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentMonth = now()->month;
        $currentYear = now()->year;

        $query = Commission::query();

        // Sales sees only their own, Admin sees all
        if ($user->isSales()) {
            $query->whereHas('deal', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        // Current month commissions
        $monthlyQuery = clone $query;
        $monthlyTotal = $monthlyQuery
            ->whereMonth('calculation_date', $currentMonth)
            ->whereYear('calculation_date', $currentYear)
            ->sum('amount');

        // Pending commissions
        $pendingQuery = clone $query;
        $pendingTotal = $pendingQuery->where('status', Commission::STATUS_PENDING)->sum('amount');

        // Total paid
        $paidQuery = clone $query;
        $paidTotal = $paidQuery->where('status', Commission::STATUS_PAID)->sum('amount');

        return response()->json([
            'monthly_total' => (float) $monthlyTotal,
            'pending_total' => (float) $pendingTotal,
            'paid_total' => (float) $paidTotal,
            'month' => $currentMonth,
            'year' => $currentYear,
        ]);
    }
}
