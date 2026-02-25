<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContactController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        try {
            $query = Contact::with(['company' => fn($q) => $q->select(['id', 'name'])])
                ->withCount('deals');

            if ($request->has('company_id')) {
                $query->where('company_id', $request->company_id);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $query->orderBy('name');

            $perPage = min((int) $request->input('per_page', 50), 100);
            return ContactResource::collection($query->simplePaginate($perPage));
        } catch (\Throwable $e) {
            Log::error('ContactController@index failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to load contacts.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'company_id' => 'required|exists:companies,id',
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'nullable|string|max:50',
                'position' => 'nullable|string|max:255',
            ]);

            $contact = DB::transaction(function () use ($validated) {
                return Contact::create($validated);
            });

            return response()->json([
                'message' => 'Contact created successfully',
                'data' => new ContactResource($contact->load('company')),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('ContactController@store failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to create contact.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function show(Contact $contact): ContactResource|JsonResponse
    {
        try {
            return new ContactResource($contact->load('company')->loadCount('deals'));
        } catch (\Throwable $e) {
            Log::error('ContactController@show failed', [
                'contact_id' => $contact->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to load contact.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function update(Request $request, Contact $contact): JsonResponse
    {
        try {
            $validated = $request->validate([
                'company_id' => 'sometimes|exists:companies,id',
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|max:255',
                'phone' => 'nullable|string|max:50',
                'position' => 'nullable|string|max:255',
            ]);

            DB::transaction(function () use ($contact, $validated) {
                $contact->update($validated);
            });

            return response()->json([
                'message' => 'Contact updated successfully',
                'data' => new ContactResource($contact->fresh()->load('company')),
            ]);
        } catch (\Throwable $e) {
            Log::error('ContactController@update failed', [
                'contact_id' => $contact->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to update contact.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function destroy(Contact $contact): JsonResponse
    {
        try {
            DB::transaction(function () use ($contact) {
                $contact->delete();
            });

            return response()->json(['message' => 'Contact deleted successfully']);
        } catch (\Throwable $e) {
            Log::error('ContactController@destroy failed', [
                'contact_id' => $contact->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to delete contact.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }
}
