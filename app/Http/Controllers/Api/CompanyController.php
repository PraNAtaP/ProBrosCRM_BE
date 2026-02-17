<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Company::with(['area', 'contacts']);

        // Filter by area_id if provided
        if ($request->has('area_id')) {
            $query->where('area_id', $request->area_id);
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $companies = $query->orderBy('name')->get();
        return CompanyResource::collection($companies);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'area_id' => 'required|exists:areas,id',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'industry' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $company = Company::create($validated);

        return response()->json([
            'message' => 'Company created successfully',
            'data' => new CompanyResource($company->load('area')),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Company $company): CompanyResource
    {
        return new CompanyResource($company->load(['area', 'contacts']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'area_id' => 'sometimes|exists:areas,id',
            'name' => 'sometimes|string|max:255',
            'address' => 'nullable|string',
            'industry' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $company->update($validated);

        return response()->json([
            'message' => 'Company updated successfully',
            'data' => new CompanyResource($company->load('area')),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Company $company): JsonResponse
    {
        $company->delete();

        return response()->json([
            'message' => 'Company deleted successfully',
        ]);
    }
}
