<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    //Show all tenants list
    public function index()
    {
        $tenants = DB::table('tenants')->orderBy('id')->get();

        return response()->json($tenants);
    }
    //Save/Create a new tenant record
    public function store(Request $request)
    {
        $data = $request->validate([
            'fname' => ['required', 'string', 'max:100'],
            'lname'  => ['required', 'string', 'max:100'],
            'email'      => ['required', 'email', 'max:255', 'unique:tenants,email'],
            'phone'      => ['nullable', 'string', 'max:30'],
        ]);
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('tenants')->insertGetId($data);
        $tenant = DB::table('tenants')->find($id);

        return response()->json($tenant, 201);
    }
    //Get tenant record by specific id
    public function show(string $id)
    {
        $tenant = DB::table('tenants')->find($id);

        if (! $tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        return response()->json($tenant);
    }
    //Update tenant record using specific id
    public function update(Request $request, string $id)
    {
        $tenant = DB::table('tenants')->find($id);

        if (! $tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        $data = $request->validate([
            'fname' => ['required', 'string', 'max:100'],
            'lname'  => ['required', 'string', 'max:100'],
            'email'      => ['required', 'email', 'max:255', Rule::unique('tenants', 'email')->ignore($id)],
            'phone'      => ['nullable', 'string', 'max:30'],
        ]);

        $data['phone'] = $data['phone'] ?? null;
        $data['updated_at'] = now();

        DB::table('tenants')->where('id', $id)->update($data);
        $tenant = DB::table('tenants')->find($id);

        return response()->json($tenant);
    }
    //Delete tenant record using specific id
    public function destroy(string $id)
    {
        $tenant = DB::table('tenants')->find($id);

        if (! $tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        DB::table('tenants')->where('id', $id)->delete();

        return response()->json(null, 204);
    }
}