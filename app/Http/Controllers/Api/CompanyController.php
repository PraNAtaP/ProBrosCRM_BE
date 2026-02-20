<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

class CompanyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        try {
            $hasFilters = $request->has('area_id') || $request->has('search');

            if (!$hasFilters) {
                $companies = Cache::remember('companies_list', 3600, function () {
                    return Company::with('area')
                        ->withCount('contacts')
                        ->orderBy('name')
                        ->get();
                });
                return CompanyResource::collection($companies);
            }

            $query = Company::with('area')->withCount('contacts');

            if ($request->has('area_id')) {
                $query->where('area_id', $request->area_id);
            }

            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $companies = $query->orderBy('name')->get();
            return CompanyResource::collection($companies);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to load companies.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'area_id' => 'required|exists:areas,id',
                'name' => 'required|string|max:255',
                'address' => 'nullable|string',
                'industry' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:50',
            ]);

            $company = Company::create($validated);
            Cache::forget('companies_list');

            return response()->json([
                'message' => 'Company created successfully',
                'data' => new CompanyResource($company->load('area')),
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to create company.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function show(Company $company): CompanyResource|JsonResponse
    {
        try {
            return new CompanyResource($company->loadCount('contacts')->load('area'));
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to load company.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        try {
            $validated = $request->validate([
                'area_id' => 'sometimes|exists:areas,id',
                'name' => 'sometimes|string|max:255',
                'address' => 'nullable|string',
                'industry' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:50',
            ]);

            $company->update($validated);
            Cache::forget('companies_list');

            return response()->json([
                'message' => 'Company updated successfully',
                'data' => new CompanyResource($company->load('area')),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to update company.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function destroy(Company $company): JsonResponse
    {
        try {
            $company->delete();
            Cache::forget('companies_list');

            return response()->json([
                'message' => 'Company deleted successfully',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to delete company.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }
}
