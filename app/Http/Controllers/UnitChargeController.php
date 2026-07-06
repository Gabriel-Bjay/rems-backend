<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnitChargeController extends Controller
{
    public function index(){
        $unit_charges = DB::table('unit_charges')->orderBy('id')->get();
        return 
            response()->json($unit_charges);
    }

    public function store(Request $request){
        $data = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'name' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('unit_charges')->insertGetId($data);
        $unit_charge = DB::table('unit_charges')->find($id);

        return 
            response()->json($unit_charge, 201);

    }

    public function show(string $id){
        $unit_charge = DB::table('unit_charges')->find($id);

        if (! $unit_charge) {
            return 
                response()->json(['message' => 'Unit charge not found.'], 404);
        }

        return 
            response()->json($unit_charge);
    }

    public function update(Request $request, string $id){
        $unit_charge = DB::table('unit_charges')->find($id);

        if (! $unit_charge) {
            return 
                response()->json(['message' => 'Unit charge not found.'], 404);
        }

        $data = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'name' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $data['updated_at'] = now();

        DB::table('unit_charges')->where('id', $id)->update($data);

        $updated_unit_charge = DB::table('unit_charges')->find($id);

        return 
            response()->json($updated_unit_charge);
    }

    public function destroy(string $id){
        $unit_charge = DB::table('unit_charges')->find($id);

        if (! $unit_charge) {
            return 
                response()->json(['message' => 'Unit charge not found.'], 404);
        }

        DB::table('unit_charges')->where('id', $id)->delete();

        return 
            response()->json([null, 204]);
    }
}
