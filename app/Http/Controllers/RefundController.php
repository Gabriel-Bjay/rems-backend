<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RefundController extends Controller
{
    public function index()
    {
        $refunds = DB::table('refunds')->orderByDesc('id')->get();

        return
            response()->json($refunds, 200);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'deposit_id' => ['nullable', 'integer', 'exists:deposits,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
            'method' => ['required', Rule::in(['cash', 'bank', 'mpesa', 'card'])],
            'refunded_at' => ['nullable', 'date'],
        ]);

        $data['deposit_id'] = $data['deposit_id'] ?? null;
        $data['reason'] = $data['reason'] ?? null;
        $data['refunded_at'] = $data['refunded_at'] ?? now();

        $data['authorised_by_user_id'] = $request->user()->id;

        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('refunds')->insertGetId($data);

        $refund = DB::table('refunds')->find($id);

        return
            response()->json($refund, 201);
    }

    public function show(string $id)
    {
        $refund = DB::table('refunds')->find($id);

        if (! $refund) {
            return 
                response()->json(['message' => 'Refund not found.'], 404);
        }

        return 
            response()->json($refund);
    }

    public function update(Request $request, string $id)
    {
        $refund = DB::table('refunds')->find($id);

        if (! $refund) {
            return response()->json(['message' => 'Refund not found.'], 404);
        }

        $data = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'deposit_id' => ['nullable', 'integer', 'exists:deposits,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
            'method' => ['required', Rule::in(['cash', 'bank', 'mpesa', 'card'])],
            'refunded_at' => ['nullable', 'date'],
        ]);

        $data['deposit_id'] = $data['deposit_id'] ?? null;
        $data['reason'] = $data['reason'] ?? null;
        $data['refunded_at'] = $data['refunded_at'] ?? now();

        $data['authorised_by_user_id'] = $request->user()->id;

        $data['updated_at'] = now();

        DB::table('refunds')->where('id', $id)->update($data);

        $updatedRefund = DB::table('refunds')->find($id);

        return 
            response()->json($updatedRefund);
    }
    public function destroy(string $id)
    {
        $refund = DB::table('refunds')->find($id);

        if (! $refund) {
            return response()->json(['message' => 'Refund not found.'], 404);
        }

        DB::table('refunds')->where('id', $id)->delete();

        return response()->json(null, 204);
    }
}
