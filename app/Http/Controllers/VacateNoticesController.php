<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VacateNoticesController extends Controller
{
    public function index(){
        $vacate_notices = DB::table('vacate_notices')->orderBy('id')->get();

        return 
            response()->json($vacate_notices);
    }

    public function store(Request $request){
        $data = $request->validate([
            'tenancy_id' => ['required', 'integer', 'exists:tenancies,id'],
            'intended_move_out_date' => ['required', 'date'],
        ]);

        $data['status'] = 'submitted';
        $data['submitted_at'] = now();
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('vacate_notices')->insertGetId($data);
        $vacate_notice = DB::table('vacate_notices')->find($id);

        return  
            response()->json($vacate_notice, 201);
    }

    public function show($id){
        $vacate_notice = DB::table('vacate_notices')->find($id);

        if(!$vacate_notice){
            return 
                response()->json(['message' => 'Vacate notice not found'], 404);
        }

        return 
            response()->json($vacate_notice, 200);
    }
    public function update(string $id, Request $request){
        $vacate_notice = DB::table('vacate_notices')->find($id);

        if(!$vacate_notice){
            return 
                response()->json(['message' => 'Vacate notice not found'], 404);
        }

        $data = $request->validate([
            'tenancy_id' => ['required', 'integer', 'exists:tenancies,id'],
            'intended_move_out_date' => ['required', 'date'],
        ]);

        $data['updated_at'] = now();

        DB::table('vacate_notices')->where('id', $id)->update($data);
        $updated_vacate_notice = DB::table('vacate_notices')->find($id);

        return 
            response()->json($updated_vacate_notice, 200);
    }

    public function destroy(string $id){
        $vacate_notice = DB::table('vacate_notices')->find($id);

        if(!$vacate_notice){
            return 
                response()->json(['message' => 'Vacate notice not found'], 404);
        }

        DB::table('vacate_notices')->where('id', $id)->delete();

        return 
            response()->json(null, 204);
    }
}
