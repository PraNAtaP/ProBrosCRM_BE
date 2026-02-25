<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AreaResource;
use App\Models\Area;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AreaController extends Controller
{
    public function index(): AnonymousResourceCollection|JsonResponse
    {
        try {
            $areas = Cache::remember('areas_list', 3600, function () {
                return Area::withCount('companies')->orderBy('name')->get();
            });
            return AreaResource::collection($areas);
        } catch (\Throwable $e) {
            Log::error('AreaController@index failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to load areas.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
            ]);

            $area = DB::transaction(function () use ($validated) {
                return Area::create($validated);
            });

            Cache::forget('areas_list');

            return response()->json([
                'message' => 'Area created successfully',
                'data' => new AreaResource($area),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('AreaController@store failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to create area.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function show(Area $area): AreaResource|JsonResponse
    {
        try {
            return new AreaResource($area->loadCount('companies'));
        } catch (\Throwable $e) {
            Log::error('AreaController@show failed', [
                'area_id' => $area->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to load area.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function update(Request $request, Area $area): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
            ]);

            DB::transaction(function () use ($area, $validated) {
                $area->update($validated);
            });

            Cache::forget('areas_list');

            return response()->json([
                'message' => 'Area updated successfully',
                'data' => new AreaResource($area->fresh()),
            ]);
        } catch (\Throwable $e) {
            Log::error('AreaController@update failed', [
                'area_id' => $area->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to update area.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function destroy(Area $area): JsonResponse
    {
        try {
            $companiesCount = $area->companies()->count();

            if ($companiesCount > 0) {
                return response()->json([
                    'message' => "Cannot delete \"{$area->name}\" because it is still used by {$companiesCount} compan" . ($companiesCount !== 1 ? 'ies' : 'y') . ". Please reassign those companies first.",
                ], 409);
            }

            DB::transaction(function () use ($area) {
                $area->delete();
            });

            Cache::forget('areas_list');

            return response()->json(['message' => 'Area deleted successfully']);
        } catch (\Throwable $e) {
            Log::error('AreaController@destroy failed', [
                'area_id' => $area->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to delete area.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }
}
