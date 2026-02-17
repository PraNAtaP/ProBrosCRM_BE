<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\Deal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of activities for a deal.
     */
    public function index(Request $request, Deal $deal): AnonymousResourceCollection|JsonResponse
    {
        $user = $request->user();

        // Sales can only view activities for their own deals
        if ($user->isSales() && $deal->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized to view activities for this deal',
            ], 403);
        }

        $activities = $deal->activityLogs()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return ActivityLogResource::collection($activities);
    }

    /**
     * Store a newly created activity for a deal.
     */
    public function store(Request $request, Deal $deal): JsonResponse
    {
        $user = $request->user();

        // Sales can only add activities to their own deals
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
    }
}
