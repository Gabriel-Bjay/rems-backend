<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UnitChargeController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
        ]);

        $query = DB::table('unit_charges')
            ->orderBy('unit_id')
            ->orderBy('name');

        if (! empty($data['unit_id'])) {
            $query->where('unit_id', $data['unit_id']);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('unit_charges', 'name')
                    ->where('unit_id', $request->input('unit_id')),
            ],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('unit_charges')->insertGetId($data);
        $charge = DB::table('unit_charges')->find($id);

        return response()->json($charge, 201);
    }

    public function show(string $id)
    {
        $charge = DB::table('unit_charges')->find($id);

        if (! $charge) {
            return response()->json([
                'message' => 'Unit charge not found.',
            ], 404);
        }

        return response()->json($charge);
    }

    public function update(Request $request, string $id)
    {
        $charge = DB::table('unit_charges')->find($id);

        if (! $charge) {
            return response()->json([
                'message' => 'Unit charge not found.',
            ], 404);
        }

        $unitId = $request->input('unit_id', $charge->unit_id);

        $data = $request->validate([
            'unit_id' => ['sometimes', 'required', 'integer', 'exists:units,id'],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('unit_charges', 'name')
                    ->where('unit_id', $unitId)
                    ->ignore($id),
            ],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0'],
        ]);

        $data['updated_at'] = now();

        DB::table('unit_charges')
            ->where('id', $id)
            ->update($data);

        return response()->json(
            DB::table('unit_charges')->find($id)
        );
    }

    public function destroy(string $id)
    {
        $charge = DB::table('unit_charges')->find($id);

        if (! $charge) {
            return response()->json([
                'message' => 'Unit charge not found.',
            ], 404);
        }

        DB::table('unit_charges')
            ->where('id', $id)
            ->delete();

        return response()->json(null, 204);
    }
}