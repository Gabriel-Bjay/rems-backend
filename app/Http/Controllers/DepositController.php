<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepositController extends Controller
{
    public function index(){
        $deposits = DB::table('deposits')->orderBy('id')->get();

        return 
            response()->json($deposits);
    }

    public function store(Request $request){
        $data = $request->validate([
            'tenancy_id' => ['required', 'integer', 'exists:tenancies,id', 'unique:deposits,tenancy_id'],
            'amount_required' => ['required', 'numeric', 'min:0'],
        ]);

        $data['amount_held'] = 0;
        $data['status'] = 'pending';
        $data['deductions'] = 0;
        $data['refund_amount'] = null;
        $data['settled_at'] = null;
        $data['confirmed_by_user_id'] = null;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('deposits')->insertGetId($data);
        $deposit = DB::table('deposits')->find($id);

        return 
            response()->json($deposit, 201);
    }
    public function show($id){
        $deposit = DB::table('deposits')->find($id);

        if(!$deposit){
            return 
                response()->json(['message' => 'Deposit not found'], 404);
        }

        return 
            response()->json($deposit);
    }
    public function update(string $id, Request $request){
        $deposit = DB::table('deposits')->find($id);

        if(!$deposit){
            return 
                response()->json(['message' => 'Deposit not found'], 404);

        }

        $data = $request->validate([
            'tenancy_id' => ['required', 'integer', 'exists:tenancies,id', 'unique:deposits,tenancy_id,'.$id],
            'amount_required' => ['required', 'numeric', 'min:0'],
        ]);

        $data['updated_at'] = now();
        
        DB::table('deposits')->where('id', $id)->update($data);
        $deposit = DB::table('deposits')->find($id);

        return 
            response()->json($deposit);
    }
    public function destroy($id){
        $deposit = DB::table('deposits')->find($id);

        if(!$deposit){
            return 
                response()->json(['message' => 'Deposit not found'], 404);
        }

        DB::table('deposits')->where('id', $id)->delete();

        return 
            response()->json(null, 204);
    }
}
