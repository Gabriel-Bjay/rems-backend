<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommissionController extends Controller
{
    
    public function index()
    {
        $commissions = DB::table('commissions')->orderByDesc('id')->get();

        return 
            response()->json($commissions);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'agent_id' => ['required', 'integer', 'exists:agents,id'],
            'payment_id' => ['required', 'integer', 'exists:payments,id'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $payment = DB::table('payments')->find($data['payment_id']);

        $data['amount'] = round($payment->amount * ($data['rate'] / 100), 2);
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('commissions')->insertGetId($data);

        $commission = DB::table('commissions')->find($id);

        return 
            response()->json($commission, 201);
    }

    public function show(string $id)
    {
        $commission = DB::table('commissions')->find($id);

        if (!$commission) {
            return response()->json(['message' => 'Commission not found'], 404);
        }

        return 
            response()->json($commission);
    }

    public function update(Request $request, string $id)
    {
        $commission = DB::table('commissions')->find($id);

        if (!$commission) {
            return response()->json(['message' => 'Commission not found'], 404);
        }

        $data = $request->validate([
            'agent_id' => ['required', 'integer', 'exists:agents,id'],
            'payment_id' => ['required', 'integer', 'exists:payments,id'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $payment = DB::table('payments')->find($data['payment_id']);

        $data['amount'] = round($payment->amount * ($data['rate'] / 100), 2);
        $data['updated_at'] = now();

        DB::table('commissions')->where('id', $id)->update($data);
        $commission = DB::table('commissions')->find($id);

        return 
            response()->json($commission);
    }

    public function destroy(string $id)
    {
        $commission = DB::table('commissions')->find($id);

        if (!$commission) {
            return response()->json(['message' => 'Commission not found'], 404);
        }

        DB::table('commissions')->where('id', $id)->delete();

        return 
            response()->json(null, 204);
    }
}
