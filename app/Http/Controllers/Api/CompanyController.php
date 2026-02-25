<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompanyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        try {
            $query = Company::with(['area' => fn($q) => $q->select(['id', 'name'])])
                ->withCount('contacts');

            if ($request->has('area_id')) {
                $query->where('area_id', $request->area_id);
            }

            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $query->orderBy('name');

            $perPage = min((int) $request->input('per_page', 50), 100);
            return CompanyResource::collection($query->simplePaginate($perPage));
        } catch (\Throwable $e) {
            Log::error('CompanyController@index failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
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

            $company = DB::transaction(function () use ($validated) {
                return Company::create($validated);
            });

            Cache::forget('companies_list');

            return response()->json([
                'message' => 'Company created successfully',
                'data' => new CompanyResource($company->load('area')),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('CompanyController@store failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to create company.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function show(Company $company): CompanyResource|JsonResponse
    {
        try {
            return new CompanyResource($company->load('area')->loadCount('contacts'));
        } catch (\Throwable $e) {
            Log::error('CompanyController@show failed', [
                'company_id' => $company->id ?? null,
                'error' => $e->getMessage(),
            ]);
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

            DB::transaction(function () use ($company, $validated) {
                $company->update($validated);
            });

            Cache::forget('companies_list');

            return response()->json([
                'message' => 'Company updated successfully',
                'data' => new CompanyResource($company->fresh()->load('area')),
            ]);
        } catch (\Throwable $e) {
            Log::error('CompanyController@update failed', [
                'company_id' => $company->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to update company.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function destroy(Company $company): JsonResponse
    {
        try {
            DB::transaction(function () use ($company) {
                $company->delete();
            });

            Cache::forget('companies_list');

            return response()->json(['message' => 'Company deleted successfully']);
        } catch (\Throwable $e) {
            Log::error('CompanyController@destroy failed', [
                'company_id' => $company->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to delete company.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }
}
