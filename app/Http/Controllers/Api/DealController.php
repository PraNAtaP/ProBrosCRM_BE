<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DealResource;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DealController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $query = Deal::with(['contact.company', 'user', 'commission']);

        // Sales can only see their own deals, Admin sees all
        if ($user->isSales()) {
            $query->where('user_id', $user->id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by user (admin only)
        if ($request->has('user_id') && $user->isAdmin()) {
            $query->where('user_id', $request->user_id);
        }

        $deals = $query->orderBy('created_at', 'desc')->get();
        return DealResource::collection($deals);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'title' => 'required|string|max:255',
            'value' => 'required|numeric|min:0',
            'status' => 'sometimes|in:' . implode(',', Deal::STATUSES),
            'description' => 'nullable|string',
        ]);

        // Set the user_id to current user
        $validated['user_id'] = $request->user()->id;
        $validated['status'] = $validated['status'] ?? Deal::STATUS_LEAD;

        $deal = Deal::create($validated);

        return response()->json([
            'message' => 'Deal created successfully',
            'data' => new DealResource($deal->load(['contact.company', 'user'])),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Deal $deal): DealResource|JsonResponse
    {
        $user = $request->user();

        // Sales can only view their own deals
        if ($user->isSales() && $deal->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized to view this deal',
            ], 403);
        }

        return new DealResource($deal->load(['contact.company', 'user', 'commission', 'activityLogs.user']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Deal $deal): JsonResponse
    {
        $user = $request->user();

        // Sales can only update their own deals
        if ($user->isSales() && $deal->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized to update this deal',
            ], 403);
        }

        $validated = $request->validate([
            'contact_id' => 'sometimes|exists:contacts,id',
            'title' => 'sometimes|string|max:255',
            'value' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:' . implode(',', Deal::STATUSES),
            'description' => 'nullable|string',
        ]);

        $deal->update($validated);

        return response()->json([
            'message' => 'Deal updated successfully',
            'data' => new DealResource($deal->load(['contact.company', 'user', 'commission'])),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Deal $deal): JsonResponse
    {
        $user = $request->user();

        // Sales can only delete their own deals
        if ($user->isSales() && $deal->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized to delete this deal',
            ], 403);
        }

        $deal->delete();

        return response()->json([
            'message' => 'Deal deleted successfully',
        ]);
    }

    /**
     * Get available deal statuses.
     */
    public function statuses(): JsonResponse
    {
        return response()->json([
            'statuses' => Deal::STATUSES,
            'colors' => Deal::STATUS_COLORS,
        ]);
    }
}
