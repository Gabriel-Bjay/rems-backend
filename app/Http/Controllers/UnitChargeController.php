<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    //Show units list
    public function index()
    {
        $units = DB::table('units')->orderBy('id')->get();

        return response()->json($units);
    }

    //Create new unit
    public function store(Request $request)
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'string', 'max:50'],
            'base_rent' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['vacant', 'occupied', 'under_maintenance'])],
            'description' => ['nullable', 'string'],
        ]);

        $data['agent_id'] = $data['agent_id'] ?? null;
        $data['status'] = $data['status'] ?? 'vacant';
        $data['description'] = $data['description'] ?? null;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('units')->insertGetId($data);
        $unit = DB::table('units')->find($id);

        return response()->json($unit, 201);
    }

    //Get unit details using specific unit id
    public function show(string $id)
    {
        $unit = DB::table('units')->find($id);

        if (! $unit) {
            return response()->json(['message' => 'Unit not found.'], 404);
        }

        return response()->json($unit);
    }

    //Edit unit details using specific unit id
    public function update(Request $request, string $id)
    {
        $unit = DB::table('units')->find($id);

        if (! $unit) {
            return response()->json(['message' => 'Unit not found.'], 404);
        }

        $data = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'string', 'max:50'],
            'base_rent' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['vacant', 'occupied', 'under_maintenance'])],
            'description' => ['nullable', 'string'],
        ]);

        $data['agent_id'] = $data['agent_id'] ?? null;
        $data['status'] = $data['status'] ?? $unit->status;
        $data['description'] = $data['description'] ?? null;
        $data['updated_at'] = now();

        DB::table('units')->where('id', $id)->update($data);
        $updatedUnit = DB::table('units')->find($id);

        return response()->json($updatedUnit);
    }

    //Delete unit using specific unit id
    public function destroy(string $id)
    {
        $unit = DB::table('units')->find($id);

        if (! $unit) {
            return response()->json(['message' => 'Unit not found.'], 404);
        }

        // A unit should not be removed while records still depend on it.
        // Keep only the checks whose foreign key uses onDelete('restrict') in your migration.
        // Drop any that you intentionally set to cascade (for example, listings).
        $hasTenancies = DB::table('tenancies')->where('unit_id', $id)->exists();
        $hasCharges = DB::table('unit_charges')->where('unit_id', $id)->exists();
        $hasTickets = DB::table('maintenance_tickets')->where('unit_id', $id)->exists();

        if ($hasTenancies || $hasCharges || $hasTickets) {
            return response()->json([
                'message' => 'This unit still has related records (tenancies, charges, or maintenance tickets) and cannot be deleted.',
            ], 409);
        }

        DB::table('units')->where('id', $id)->delete();

        return response()->json(null, 204);
    }
}