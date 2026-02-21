<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        try {
            $query = Contact::with('company')->withCount('deals');

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
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:50',
                'position' => 'nullable|string|max:255',
            ]);

            $contact = Contact::create($validated);

            return response()->json([
                'message' => 'Contact created successfully',
                'data' => new ContactResource($contact->load('company')),
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to create contact.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function show(Contact $contact): ContactResource|JsonResponse
    {
        try {
            return new ContactResource($contact->load(['company', 'deals'])->loadCount('deals'));
        } catch (\Throwable $e) {
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
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:50',
                'position' => 'nullable|string|max:255',
            ]);

            $contact->update($validated);

            return response()->json([
                'message' => 'Contact updated successfully',
                'data' => new ContactResource($contact->load('company')),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to update contact.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function destroy(Contact $contact): JsonResponse
    {
        try {
            $contact->delete();

            return response()->json([
                'message' => 'Contact deleted successfully',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to delete contact.',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }
}
