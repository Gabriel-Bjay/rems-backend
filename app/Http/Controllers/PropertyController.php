<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyController extends Controller
{
    //Show properties list
    public function index(Request $request)
    {
        $user = $request->user();

        $query = DB::table('properties');

        // an owner sees only their own properties
        if ($user->role === 'owner') {
            $owner = DB::table('owners')->where('user_id', $user->id)->first();
            $query->where('owner_id', $owner->id ?? 0);
        }

        // admin falls through and sees everything

        $properties = $query->orderByDesc('id')->get();

        return response()->json($properties, 200);
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
        $property = DB::table('properties')->find($id);

        if (! $property) {
            return response()->json(['message' => 'Property not found.'], 404);
        }

        $user = $request->user();

        if ($user->role === 'owner') {
            $owner = DB::table('owners')->where('user_id', $user->id)->first();

            if (! $owner || $property->owner_id != $owner->id) {
                return response()->json(['message' => 'You do not have access to this property.'], 403);
            }
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