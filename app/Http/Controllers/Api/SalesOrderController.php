<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalesOrderResource;
use App\Models\SalesOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesOrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        try {
            $user = $request->user();
            $tab = $request->input('tab', 'current');

            $query = SalesOrder::with([
                'company' => fn($q) => $q->select(['id', 'name', 'trading_name']),
                'contact' => fn($q) => $q->select(['id', 'name', 'email']),
                'user' => fn($q) => $q->select(['id', 'name']),
            ]);

            if ($user->isSales()) {
                $query->where('user_id', $user->id);
            }

            // Tab-based filtering
            switch ($tab) {
                case 'current':
                    $query->whereIn('status', [SalesOrder::STATUS_PENDING, SalesOrder::STATUS_PROCESSING]);
                    break;
                case 'delivered':
                    $query->where('status', SalesOrder::STATUS_DELIVERED);
                    break;
                case 'favorites':
                    $query->where('is_favorite', true);
                    break;
                case 'cancelled':
                    $query->where('status', SalesOrder::STATUS_CANCELLED);
                    break;
                case 'commented':
                    $query->whereNotNull('additional_notes')->where('additional_notes', '!=', '');
                    break;
                case 'second_run':
                    $query->where('is_second_run', true);
                    break;
                case 'price_sync':
                    $query->where('needs_price_sync', true);
                    break;
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                      ->orWhereHas('company', fn($cq) => $cq->where('name', 'like', "%{$search}%")
                          ->orWhere('trading_name', 'like', "%{$search}%"));
                });
            }

            $query->orderBy('created_at', 'desc');

            $perPage = min((int) $request->input('per_page', 25), 100);
            return SalesOrderResource::collection($query->simplePaginate($perPage));
        } catch (\Throwable $e) {
            Log::error('SalesOrderController@index failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to load sales orders.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'company_id' => 'required|exists:companies,id',
                'contact_id' => 'nullable|exists:contacts,id',
                'total_amount' => 'required|numeric|min:0',
                'status' => 'nullable|in:' . implode(',', SalesOrder::STATUSES),
                'delivery_date' => 'nullable|date',
                'additional_notes' => 'nullable|string|max:5000',
                'is_favorite' => 'nullable|boolean',
                'is_second_run' => 'nullable|boolean',
                'needs_price_sync' => 'nullable|boolean',
            ]);

            $validated['user_id'] = $request->user()->id;
            $validated['order_number'] = SalesOrder::generateOrderNumber();
            $validated['status'] = $validated['status'] ?? SalesOrder::STATUS_PENDING;

            $order = DB::transaction(function () use ($validated) {
                return SalesOrder::create($validated);
            });

            return response()->json([
                'message' => 'Sales order created successfully',
                'data' => new SalesOrderResource($order->load(['company', 'contact', 'user'])),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('SalesOrderController@store failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to create sales order.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function show(SalesOrder $salesOrder): SalesOrderResource|JsonResponse
    {
        try {
            return new SalesOrderResource($salesOrder->load(['company', 'contact', 'user']));
        } catch (\Throwable $e) {
            Log::error('SalesOrderController@show failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to load sales order.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function update(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        try {
            $user = $request->user();
            if ($user->isSales() && $salesOrder->user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $validated = $request->validate([
                'company_id' => 'sometimes|exists:companies,id',
                'contact_id' => 'nullable|exists:contacts,id',
                'total_amount' => 'sometimes|numeric|min:0',
                'status' => 'sometimes|in:' . implode(',', SalesOrder::STATUSES),
                'delivery_date' => 'nullable|date',
                'additional_notes' => 'nullable|string|max:5000',
                'is_favorite' => 'nullable|boolean',
                'is_second_run' => 'nullable|boolean',
                'needs_price_sync' => 'nullable|boolean',
            ]);

            // Auto-set timestamps on status change
            if (isset($validated['status'])) {
                if ($validated['status'] === SalesOrder::STATUS_DELIVERED && !$salesOrder->delivered_at) {
                    $validated['delivered_at'] = now();
                }
                if ($validated['status'] === SalesOrder::STATUS_CANCELLED && !$salesOrder->cancelled_at) {
                    $validated['cancelled_at'] = now();
                }
            }

            DB::transaction(function () use ($salesOrder, $validated) {
                $salesOrder->update($validated);
            });

            return response()->json([
                'message' => 'Sales order updated successfully',
                'data' => new SalesOrderResource($salesOrder->fresh()->load(['company', 'contact', 'user'])),
            ]);
        } catch (\Throwable $e) {
            Log::error('SalesOrderController@update failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to update sales order.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function destroy(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        try {
            $user = $request->user();
            if ($user->isSales() && $salesOrder->user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            DB::transaction(function () use ($salesOrder) {
                $salesOrder->delete();
            });

            return response()->json(['message' => 'Sales order deleted successfully']);
        } catch (\Throwable $e) {
            Log::error('SalesOrderController@destroy failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to delete sales order.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    /**
     * Mass update status for multiple orders.
     */
    public function massStatus(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:sales_orders,id',
                'status' => 'required|in:' . implode(',', SalesOrder::STATUSES),
            ]);

            $extra = [];
            if ($validated['status'] === SalesOrder::STATUS_DELIVERED) {
                $extra['delivered_at'] = now();
            }
            if ($validated['status'] === SalesOrder::STATUS_CANCELLED) {
                $extra['cancelled_at'] = now();
            }

            $count = DB::transaction(function () use ($validated, $extra) {
                return SalesOrder::whereIn('id', $validated['ids'])
                    ->update(array_merge(['status' => $validated['status']], $extra));
            });

            return response()->json([
                'message' => "{$count} orders updated to {$validated['status']}",
                'updated_count' => $count,
            ]);
        } catch (\Throwable $e) {
            Log::error('SalesOrderController@massStatus failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to update orders.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    /**
     * Toggle favorite for a single order.
     */
    public function toggleFavorite(SalesOrder $salesOrder): JsonResponse
    {
        try {
            $salesOrder->update(['is_favorite' => !$salesOrder->is_favorite]);

            return response()->json([
                'message' => $salesOrder->is_favorite ? 'Added to favorites' : 'Removed from favorites',
                'is_favorite' => $salesOrder->is_favorite,
            ]);
        } catch (\Throwable $e) {
            Log::error('SalesOrderController@toggleFavorite failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to toggle favorite.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }
}
