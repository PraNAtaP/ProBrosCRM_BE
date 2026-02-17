<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AreaResource;
use App\Models\Area;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AreaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        $areas = Area::with('companies')->orderBy('name')->get();
        return AreaResource::collection($areas);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $area = Area::create($validated);

        return response()->json([
            'message' => 'Area created successfully',
            'data' => new AreaResource($area),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Area $area): AreaResource
    {
        return new AreaResource($area->load('companies'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Area $area): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);

        $area->update($validated);

        return response()->json([
            'message' => 'Area updated successfully',
            'data' => new AreaResource($area),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Area $area): JsonResponse
    {
        $area->delete();

        return response()->json([
            'message' => 'Area deleted successfully',
        ]);
    }
}
