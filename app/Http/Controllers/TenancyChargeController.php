<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenancyChargeController extends Controller
{
    //Show list of all tenancy charges
    public function index(){
        $tenancy_charges = DB::table('tenancy_charges')->orderBy('id')->get();
        return 
            response()->json($tenancy_charges);
    }
    //Save new tenancy charge
    public function store(Request $request){
        $data = $request->validate([
            'tenancy_id' => ['required', 'integer', 'exists:tenancies,id'],
            'name' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $data['tenancy_id'] = $data['tenancy_id'] ?? null;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('tenancy_charges')->insertGetId($data);
        $tenancy_charge = DB::table('tenancy_charges')->find($id);
        return 
            response()->json($tenancy_charge);
    }
    //Return single tenancy charge record using specific id
    public function show(string $id){
        $tenancy_charge = DB::table('tenancy_charges')->find($id);

        if (! $tenancy_charge) {
            return 
                response()->json(['message' => 'Tenancy charge not found.'], 404);
        }

        return 
            response()->json($tenancy_charge);
    }
    //Update tenancy charge record using specific id
    public function update(Request $request, string $id){
        $tenancy_charge = DB::table('tenancy_charges')->find($id);

        if (! $tenancy_charge) {
            return 
                response()->json(['message' => 'Tenancy charge not found.'], 404);
        }

        $data = $request->validate([
            'tenancy_id' => ['required', 'integer', 'exists:tenancies,id'],
            'name' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $data['tenancy_id'] = $data['tenancy_id'] ?? null;
        $data['updated_at'] = now();
        
        DB::table('tenancy_charges')->where('id', $id)->update($data);
        $updated_tenancy_charge = DB::table('tenancy_charges')->find($id);

        return 
            response()->json($updated_tenancy_charge);
    }
    //Delete tenancy charge record using specific id
    public function destroy(string $id){
        $tenancy_charge = DB::table('tenancy_charges')->find($id);

        if (! $tenancy_charge) {
            return 
                response()->json(['message' => 'Tenancy charge not found.'], 404);
        }

        DB::table('tenancy_charges')->where('id', $id)->delete();
        return 
            response()->json(null, 204);
    }
}