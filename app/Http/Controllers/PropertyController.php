<?php

namespace App\Http\Controllers;

use App\Services\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyController extends Controller
{
    //Show properties list
    public function index(Request $request)
    {
        return response()->json($this->visible($request)->orderByDesc('properties.id')->get(), 200);
    }

    //Create new property
    public function store(Request $request)
    {
        $data = $request->validate([
            'owner_id' => ['required', 'integer', 'exists:owners,id'],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $data['agent_id'] = $data['agent_id'] ?? null;
        $data['description'] = $data['description'] ?? null;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('properties')->insertGetId($data);
        $property = DB::table('properties')->find($id);

        return 
            response()->json($property, 201);
    }

    //Get property details 
    public function show(Request $request, string $id)
    {
        $property = $this->visible($request)->where('properties.id', $id)->first();

        if (! $property) {
            return response()->json(['message' => 'Property not found.'], 404);
        }

        return response()->json($property);
    }

    //Edit property details using specific property id
    public function update(Request $request, string $id)
    {
        $property = DB::table('properties')->find($id);

        if (! $property) {
            return
                response()->json(['message' => 'Property not found.'], 404);
        }

        $data = $request->validate([
            'owner_id' => ['required', 'integer', 'exists:owners,id'],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $data['agent_id'] = $data['agent_id'] ?? null;
        $data['description'] = $data['description'] ?? null;
        $data['updated_at'] = now();

        DB::table('properties')->where('id', $id)->update($data);
        $property = DB::table('properties')->find($id);

        return 
            response()->json($property);
    }

    //Delete property using specific property id
    public function destroy(Request $request, string $id)
    {
        $property = DB::table('properties')->find($id);

        if (! $property) {
            return response()->json(['message' => 'Property not found.'], 404);
        }

        // a property cannot be removed while units still reference it
        $hasUnits = DB::table('units')->where('property_id', $id)->exists();

        if ($hasUnits) {
            return response()->json([
                'message' => 'This property still has units and cannot be deleted. Remove its units first.',
            ], 409);
        }

        DB::table('properties')->where('id', $id)->delete();

        return response()->json(null, 204);
    }

    /** Properties the user may see, with owner, agent and occupancy figures. */
    private function visible(Request $request): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('properties')
            ->leftJoin('owners', 'owners.id', '=', 'properties.owner_id')
            ->leftJoin('agents', 'agents.id', '=', 'properties.agent_id')
            ->select(
                'properties.*',
                'owners.fname as owner_fname',
                'owners.lname as owner_lname',
                'agents.fname as agent_fname',
                'agents.lname as agent_lname',
                DB::raw('(SELECT COUNT(*) FROM units WHERE units.property_id = properties.id) as unit_count'),
                DB::raw("(SELECT COUNT(*) FROM units WHERE units.property_id = properties.id
                    AND units.status = 'occupied') as occupied_count"),
                DB::raw('(SELECT COALESCE(SUM(base_rent), 0) FROM units
                    WHERE units.property_id = properties.id) as rent_roll'),
            );

        return Access::limit($query, 'properties.id', Access::for($request->user())->propertyIds());
    }
}