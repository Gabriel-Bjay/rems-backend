<?php

namespace App\Http\Controllers;

use App\Services\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    //Show units list
    public function index(Request $request){
        return response()->json($this->visible($request)->orderBy('units.id')->get());
    }

    //Create new unit
    public function store(Request $request){
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

        return 
            response()->json($unit, 201);

    }

    //Get unit details using specific unit id
    public function show(Request $request, string $id){
        $unit = $this->visible($request)->where('units.id', $id)->first();

        if (! $unit) {
            return 
                response()->json(['message' => 'Unit not found.'], 404);
        }

        return 
            response()->json($unit);

    }

    //Edit unit details using specific unit id
    public function update(Request $request, string $id){
        $unit = DB::table('units')->find($id);

        if (! $unit) {
            return 
                response()->json(['message' => 'Unit not found.'], 404);
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

        return 
            response()->json($updatedUnit);
    }

    //Delete unit using specific unit id
    public function destroy(string $id){
        $unit = DB::table('units')->find($id);

        if (! $unit) {
            return 
                response()->json(['message' => 'Unit not found.'], 404);
        }

        DB::table('units')->where('id', $id)->delete();

        return
            response()->json(['message' => 'Unit deleted successfully.']);
    }

    /** Units the user may see, with their property and current tenant. */
    private function visible(Request $request): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('units')
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->leftJoin('tenancies', fn ($join) => $join
                ->on('tenancies.unit_id', '=', 'units.id')
                ->where('tenancies.status', '=', 'active'))
            ->leftJoin('tenants', 'tenants.id', '=', 'tenancies.tenant_id')
            ->select(
                'units.*',
                'properties.name as property_name',
                'tenancies.id as active_tenancy_id',
                'tenants.fname as tenant_fname',
                'tenants.lname as tenant_lname',
            );

        return Access::limit($query, 'units.id', Access::for($request->user())->unitIds());
    }
}
