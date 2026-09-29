<?php

namespace App\Http\Controllers;

use App\Services\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepositController extends Controller
{
    public function index(Request $request){
        return response()->json($this->visible($request)->orderBy('deposits.id')->get());
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
    public function show(Request $request, $id){
        $deposit = $this->visible($request)->where('deposits.id', $id)->first();

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

    /** Deposits the user may see, with tenant and unit names. */
    private function visible(Request $request): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('deposits')
            ->join('tenancies', 'tenancies.id', '=', 'deposits.tenancy_id')
            ->join('tenants', 'tenants.id', '=', 'tenancies.tenant_id')
            ->join('units', 'units.id', '=', 'tenancies.unit_id')
            ->select(
                'deposits.*',
                'tenants.fname as tenant_fname',
                'tenants.lname as tenant_lname',
                'units.name as unit_name',
            );

        return Access::limit($query, 'deposits.tenancy_id', Access::for($request->user())->tenancyIds());
    }
}
