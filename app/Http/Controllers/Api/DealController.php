<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DealResource;
use App\Models\Deal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

class DealController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        try {
            $user = $request->user();
            $query = Deal::select([
                    'id', 'contact_id', 'user_id', 'title', 'value', 'status', 'description', 'created_at', 'updated_at'
                ])
                ->with([
                    'contact' => fn($q) => $q->select(['id', 'name', 'email', 'company_id']),
                    'contact.company' => fn($q) => $q->select(['id', 'name']),
                    'user' => fn($q) => $q->select(['id', 'name']),
                    'commission' => fn($q) => $q->select(['id', 'deal_id', 'amount', 'status']),
                ]);

            if ($user->isSales()) {
                $query->where('user_id', $user->id);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('user_id') && $user->isAdmin()) {
                $query->where('user_id', $request->user_id);
            }

            $deals = $query->orderBy('created_at', 'desc')->get();
            return DealResource::collection($deals);
        } catch (\Throwable $e) {
            Log::error('DealController@index failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to load deals.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function show(Request $request, Deal $deal): DealResource|JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->isSales() && $deal->user_id !== $user->id) {
                return response()->json([
                    'message' => 'Unauthorized to view this deal',
                ], 403);
            }

            return new DealResource($deal->load(['contact.company', 'user', 'commission', 'activityLogs.user']));
        } catch (\Throwable $e) {
            Log::error('DealController@show failed', [
                'deal_id' => $deal->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to load deal.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'contact_id' => 'required|exists:contacts,id',
                'title' => 'required|string|max:255',
                'value' => 'required|numeric|min:0',
                'status' => 'nullable|in:' . implode(',', Deal::STATUSES),
                'description' => 'nullable|string',
            ]);

            $validated['user_id'] = $request->user()->id;

            if (!isset($validated['status'])) {
                $validated['status'] = Deal::STATUS_LEAD;
            }

            $deal = Deal::create($validated);

            return response()->json([
                'message' => 'Deal created successfully',
                'data' => new DealResource($deal->load(['contact.company', 'user'])),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('DealController@store failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to create deal.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function update(Request $request, Deal $deal): JsonResponse
    {
        try {
            $user = $request->user();

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
        } catch (\Throwable $e) {
            Log::error('DealController@update failed', [
                'deal_id' => $deal->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to update deal.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function destroy(Request $request, Deal $deal): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->isSales() && $deal->user_id !== $user->id) {
                return response()->json([
                    'message' => 'Unauthorized to delete this deal',
                ], 403);
            }

            $deal->delete();

            return response()->json([
                'message' => 'Deal deleted successfully',
            ]);
        } catch (\Throwable $e) {
            Log::error('DealController@destroy failed', [
                'deal_id' => $deal->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to delete deal.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }
}
