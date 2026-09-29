<?php

namespace App\Http\Controllers;

use App\Services\Access;
use App\Services\Billing;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TenancyController extends Controller
{
    // Get and return a list of tenancies.

    public function index(Request $request)
    {
        return response()->json($this->visible($request)->orderBy('tenancies.id')->get());
    }

    // Validate and store a new tenancy.

    public function store(Request $request)
    {
        $data = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'drafted_by_agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'billing_cycle' => ['nullable', Rule::in(['monthly', 'quarterly', 'annually'])],
        ]);

        $data['drafted_by_agent_id'] = $data['drafted_by_agent_id'] ?? null;
        $data['end_date'] = $data['end_date'] ?? null;
        $data['billing_cycle'] = $data['billing_cycle'] ?? 'monthly';
        $data['status'] = 'draft';
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('tenancies')->insertGetId($data);

        return response()->json(
            DB::table('tenancies')->find($id),
            201
        );
    }

    // Get and return a specific tenancy.
    public function show(Request $request, string $id)
    {
        $tenancy = $this->visible($request)->where('tenancies.id', $id)->first();

        if (! $tenancy) {
            return response()->json([
                'message' => 'Tenancy not found.',
            ], 404);
        }

        return response()->json($tenancy);
    }

    // Validate and update a tenancy.

    public function update(Request $request, string $id)
    {
        $tenancy = DB::table('tenancies')->find($id);

        if (! $tenancy) {
            return response()->json([
                'message' => 'Tenancy not found.',
            ], 404);
        }

        $data = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'drafted_by_agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'billing_cycle' => ['nullable', Rule::in(['monthly', 'quarterly', 'annually'])],
        ]);

        $data['drafted_by_agent_id'] = $data['drafted_by_agent_id'] ?? null;
        $data['end_date'] = $data['end_date'] ?? null;
        $data['billing_cycle'] = $data['billing_cycle'] ?? 'monthly';
        $data['updated_at'] = now();

        DB::table('tenancies')
            ->where('id', $id)
            ->update($data);

        return response()->json(
            DB::table('tenancies')->find($id)
        );
    }

    // Delete a tenancy
    public function destroy(string $id)
    {
        $tenancy = DB::table('tenancies')->find($id);

        if (! $tenancy) {
            return response()->json([
                'message' => 'Tenancy not found.',
            ], 404);
        }

        if ($tenancy->status === 'active') {
            return response()->json([
                'message' => 'Active tenancies cannot be deleted. End the tenancy first.',
            ], 422);
        }

        DB::table('tenancies')
            ->where('id', $id)
            ->delete();

        return response()->json(null, 204);
    }

    // Activate a tenancy.
    public function activate(Request $request, string $id, Billing $billing)
    {
        $tenancy = $this->visible($request)->where('tenancies.id', $id)->first();

        if (! $tenancy) {
            return response()->json([
                'message' => 'Tenancy not found.',
            ], 404);
        }

        if ($tenancy->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft tenancies can be activated.',
            ], 422);
        }

        $unit = DB::table('units')
            ->where('id', $tenancy->unit_id)
            ->first();

        if (! $unit) {
            return response()->json([
                'message' => 'Unit not found.',
            ], 404);
        }

        if ($unit->status !== 'vacant') {
            return response()->json([
                'message' => 'This unit is not vacant.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($tenancy) {

                DB::table('tenancies')
                    ->where('id', $tenancy->id)
                    ->update([
                        'status' => 'active',
                        'updated_at' => now(),
                    ]);

                DB::table('units')
                    ->where('id', $tenancy->unit_id)
                    ->update([
                        'status' => 'occupied',
                        'updated_at' => now(),
                    ]);
            });
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'This unit already has an active tenancy.',
            ], 422);
        }

        // Move-in paperwork: the deposit owed and the first period's rent,
        // due before a future move-in date as well as for an ongoing one.
        $tenancy = DB::table('tenancies')->find($id);
        $billing->openDeposit($tenancy);
        $billFrom = CarbonImmutable::parse($tenancy->start_date)->max(CarbonImmutable::today());
        [$periodStart] = $billing->periodFor($tenancy, $billFrom);
        $billing->invoiceFor($tenancy, $periodStart);

        return response()->json($tenancy);
    }

    // End a tenancy.
    public function end(Request $request, string $id)
    {
        $tenancy = $this->visible($request)->where('tenancies.id', $id)->first();

        if (! $tenancy) {
            return response()->json([
                'message' => 'Tenancy not found.',
            ], 404);
        }

        if ($tenancy->status !== 'active') {
            return response()->json([
                'message' => 'Only active tenancies can be ended.',
            ], 422);
        }

        DB::transaction(function () use ($tenancy) {

            DB::table('tenancies')
                ->where('id', $tenancy->id)
                ->update([
                    'status' => 'ended',
                    // Ending early moves the end date forward to today.
                    'end_date' => min($tenancy->end_date ?? now()->toDateString(), now()->toDateString()),
                    'updated_at' => now(),
                ]);

            DB::table('units')
                ->where('id', $tenancy->unit_id)
                ->update([
                    'status' => 'vacant',
                    'updated_at' => now(),
                ]);
        });

        return response()->json(
            DB::table('tenancies')->find($id)
        );
    }

    /** Tenancies the user may see, with tenant, unit and property names. */
    private function visible(Request $request): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('tenancies')
            ->join('tenants', 'tenants.id', '=', 'tenancies.tenant_id')
            ->join('units', 'units.id', '=', 'tenancies.unit_id')
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->select(
                'tenancies.*',
                'tenants.fname as tenant_fname',
                'tenants.lname as tenant_lname',
                'units.name as unit_name',
                'units.base_rent',
                'properties.name as property_name',
            );

        return Access::limit($query, 'tenancies.id', Access::for($request->user())->tenancyIds());
    }
}
