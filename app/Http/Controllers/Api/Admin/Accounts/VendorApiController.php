<?php

namespace App\Http\Controllers\Api\Admin\Accounts;

use App\Http\Controllers\Api\ApiController;
use App\Models\AccountsVendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorApiController extends ApiController
{
    /**
     * Display a listing of vendors.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AccountsVendor::query()->latest();

        $user = auth('api')->user();
        if ($user && $user->company_id) {
            $query->where(function ($q) use ($user) {
                $q->where('company_id', $user->company_id)
                    ->orWhereNull('company_id');
            });
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', strtolower($request->status));
        }

        // Return all options when requested without pagination (e.g. for dropdowns)
        if ($request->boolean('all')) {
            return $this->success($query->get());
        }

        $perPage = max(1, (int) $request->input('per_page', 15));
        return $this->success($query->paginate($perPage));
    }

    /**
     * Store a newly created vendor.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'            => 'required|string|max:255',
            'company_name'    => 'nullable|string|max:255',
            'email'           => 'nullable|email|max:255',
            'phone'           => 'nullable|string|max:50',
            'mobile'          => 'nullable|string|max:50',
            'gstin_pan'       => 'nullable|string|max:50',
            'currency'        => 'nullable|string|max:20',
            'opening_balance' => 'nullable|numeric',
            'payment_terms'   => 'nullable|string|max:100',
            'status'          => 'nullable|in:active,inactive,Active,Inactive',
            'remarks'         => 'nullable|string',
        ]);

        $user = auth('api')->user();

        $vendor = AccountsVendor::create([
            'company_id'      => $user?->company_id,
            'name'            => $request->name,
            'company_name'    => $request->company_name,
            'email'           => $request->email,
            'phone'           => $request->phone,
            'mobile'          => $request->mobile,
            'gstin_pan'       => $request->gstin_pan,
            'currency'        => $request->currency ?? 'INR',
            'opening_balance' => (float) ($request->opening_balance ?? 0),
            'payment_terms'   => $request->payment_terms ?? 'Due on receipt',
            'status'          => strtolower($request->status ?? 'active') === 'inactive' ? 'inactive' : 'active',
            'remarks'         => $request->remarks,
            'created_by'      => $user?->id,
        ]);

        return $this->success($vendor, 'Vendor created successfully', 201);
    }

    /**
     * Display the specified vendor.
     */
    public function show($id): JsonResponse
    {
        $vendor = AccountsVendor::find($id);
        if (!$vendor) {
            return $this->error('Vendor not found', 404);
        }

        return $this->success($vendor);
    }

    /**
     * Update the specified vendor.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $vendor = AccountsVendor::find($id);
        if (!$vendor) {
            return $this->error('Vendor not found', 404);
        }

        $request->validate([
            'name'            => 'nullable|string|max:255',
            'company_name'    => 'nullable|string|max:255',
            'email'           => 'nullable|email|max:255',
            'phone'           => 'nullable|string|max:50',
            'mobile'          => 'nullable|string|max:50',
            'gstin_pan'       => 'nullable|string|max:50',
            'currency'        => 'nullable|string|max:20',
            'opening_balance' => 'nullable|numeric',
            'payment_terms'   => 'nullable|string|max:100',
            'status'          => 'nullable|in:active,inactive,Active,Inactive',
            'remarks'         => 'nullable|string',
        ]);

        $user = auth('api')->user();

        $vendor->update(array_filter([
            'name'            => $request->name ?? $vendor->name,
            'company_name'    => $request->company_name ?? $vendor->company_name,
            'email'           => $request->email ?? $vendor->email,
            'phone'           => $request->phone ?? $vendor->phone,
            'mobile'          => $request->mobile ?? $vendor->mobile,
            'gstin_pan'       => $request->gstin_pan ?? $vendor->gstin_pan,
            'currency'        => $request->currency ?? $vendor->currency,
            'opening_balance' => $request->has('opening_balance') ? (float) $request->opening_balance : $vendor->opening_balance,
            'payment_terms'   => $request->payment_terms ?? $vendor->payment_terms,
            'status'          => $request->has('status') ? (strtolower($request->status) === 'inactive' ? 'inactive' : 'active') : $vendor->status,
            'remarks'         => $request->remarks ?? $vendor->remarks,
            'updated_by'      => $user?->id,
        ], fn($v) => !is_null($v)));

        return $this->success($vendor->fresh(), 'Vendor updated successfully');
    }

    /**
     * Delete the specified vendor.
     */
    public function destroy($id): JsonResponse
    {
        $vendor = AccountsVendor::find($id);
        if (!$vendor) {
            return $this->error('Vendor not found', 404);
        }

        $vendor->delete();
        return $this->success(null, 'Vendor deleted successfully');
    }
}
