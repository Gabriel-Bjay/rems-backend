<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyController extends Controller
{
    //Show properties list
    public function index()
    {
        $properties = DB::table('properties')->orderBy('id')->get();

        return 
            response()->json($properties);
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

    //Get property details using specific property id
    public function show(string $id)
    {
        $property = DB::table('properties')->find($id);

        if (! $property) {
            return 
                response()->json(['message' => 'Property not found.'], 404);
        }

        return 
            response()->json($property);
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
    public function destroy(string $id)
    {
        $property = DB::table('properties')->find($id);

        if (! $property) {
            return 
                response()->json(['message' => 'Property not found.'], 404);
        }

        DB::table('properties')->where('id', $id)->delete();

        return 
            response()->json(null, 204);
    }
}