<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ApartmentController extends Controller
{
    public function index()
    {
        $apartments = DB::table('apartments')->orderBy('id')->get();

        return
            response()->json($apartments)
        ;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'address'     => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'rent_amount' => ['required', 'numeric', 'min:0'],
            'owner_id'    => ['required', 'integer', 'exists:owners,id'],
            'agent_id'    => ['nullable', 'integer', 'exists:agents,id'],
            'tenant_id'   => ['nullable', 'integer', 'exists:tenants,id', 'unique:apartments,tenant_id'],
        ]);

        $apartment = $this->prepare($data);
        $apartment['created_at'] = now();
        $apartment['updated_at'] = now();

        $id = DB::table('apartments')->insertGetId($apartment);
        $fresh = DB::table('apartments')->find($id);

        return 
            response()->json($fresh, 201)
        ;
    }

    public function show(string $id)
    {
        $apartment = DB::table('apartments')->find($id);

        if (! $apartment) {
            return 
                response()->json(['message' => 'Apartment not found.'], 404)
            ;
        }

        return 
            response()->json($apartment)
        ;
    }

    public function update(Request $request, string $id)
    {
        $apartment = DB::table('apartments')->find($id);

        if (! $apartment) {
            return 
                response()->json(['message' => 'Apartment not found.'], 404)
            ;
        }

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'address'     => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'rent_amount' => ['required', 'numeric', 'min:0'],
            'owner_id'    => ['required', 'integer', 'exists:owners,id'],
            'agent_id'    => ['nullable', 'integer', 'exists:agents,id'],
            'tenant_id'   => ['nullable', 'integer', 'exists:tenants,id', Rule::unique('apartments', 'tenant_id')->ignore($id)],
        ]);

        $changes = $this->prepare($data);
        $changes['updated_at'] = now();

        DB::table('apartments')->where('id', $id)->update($changes);
        $fresh = DB::table('apartments')->find($id);

        return 
            response()->json($fresh)
        ;
    }

    public function destroy(string $id)
    {
        $owner = DB::table('owners')->find($id);

        if (! $owner) {
            return response()->json(['message' => 'Owner not found.'], 404);
        }

        $hasApartments = DB::table('apartments')->where('owner_id', $id)->exists();

        if ($hasApartments) {
            return response()->json([
                'message' => 'This owner still has apartments and cannot be deleted. Reassign or delete those apartments first.',
            ], 409);
        }

        DB::table('owners')->where('id', $id)->delete();

        return response()->json(null, 204);
    }

    private function prepare(array $data): array
    {
        $data['description'] = $data['description'] ?? null;
        $data['agent_id']    = $data['agent_id'] ?? null;
        $data['tenant_id']   = $data['tenant_id'] ?? null;
        $data['status'] = $data['tenant_id'] ? 'occupied' : 'vacant';

        return $data;
    }
}