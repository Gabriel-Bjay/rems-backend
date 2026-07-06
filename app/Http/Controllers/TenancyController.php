<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TenancyController extends Controller
{
    // Get and return a list of tenancies
    public function index()
    {
        $tenancies = DB::table('tenancies')->orderBy('id')->get();
        
        return 
            response()->json($tenancies);
        
    }
    // Validate and store a new tenancy
    public function store(Request $request)
    {
        $data = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'drafted_by_agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'billing_cycle' => ['nullable', Rule::in(['monthly', 'quarterly', 'annually']) ],
        ]);

        $data['drafted_by_agent_id'] = $data['drafted_by_agent_id'] ?? null;
        $data['end_date'] = $data['end_date'] ?? null;
        $data['billing_cycle'] = $data['billing_cycle'] ?? 'monthly';
        $data['status'] = 'draft';
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('tenancies')->insertGetId($data);
        $tenancy = DB::table('tenancies')->find($id);

        return 
            response()->json($tenancy, 201);
    }
    // Get and return a specific tenancy by ID
    public function show(string $id)
    {
        $tenancy = DB::table('tenancies')->find($id);

        if (! $tenancy) {
            return 
                response()->json(['message' => 'Tenancy not found.'], 404);
        }

        return 
            response()->json($tenancy);
    }
    // Validate and update a specific tenancy by ID           
    public function update(Request $request, string $id)
    {
        $tenancy = DB::table('tenancies')->find($id);

        if (! $tenancy) {
            return 
                response()->json(['message' => 'Tenancy not found.'], 404);
        }

        $data = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'drafted_by_agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'billing_cycle' => ['nullable', Rule::in(['monthly', 'quarterly', 'annually']) ],
        ]);

        $data['drafted_by_agent_id'] = $data['drafted_by_agent_id'] ?? null;
        $data['end_date'] = $data['end_date'] ?? null;
        $data['billing_cycle'] = $data['billing_cycle'] ?? 'monthly';
        $data['updated_at'] = now();

        DB::table('tenancies')->where('id', $id)->update($data);
        $tenancy = DB::table('tenancies')->find($id);

        return 
            response()->json($tenancy);
    }
    //Delete a specific tenancy by ID
    public function destroy(string $id)
    {
        $tenancy = DB::table('tenancies')->find($id);

        if (! $tenancy) {
            return 
                response()->json(['message' => 'Tenancy not found.'], 404);
        }

        DB::table('tenancies')->where('id', $id)->delete();

        return 
            response()->json(null, 204);
    }
}
