<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    /**
     * Display a listing of payments.
     */
    public function index()
    {
        $payments = DB::table('payments')->orderByDesc('id')->get();

        return 
            response()->json($payments, 200);
    }

    /**
     * Store a newly created payment in storage.
     */
     public function store(Request $request)
    {
        $data = $request->validate([
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'tenancy_id' => ['nullable', 'integer', 'exists:tenancies,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(['cash', 'bank', 'mpesa', 'card'])],
            'gateway_reference' => ['nullable', 'string', 'max:100', 'unique:payments,gateway_reference'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $data['tenant_id'] = $data['tenant_id'] ?? null;
        $data['tenancy_id'] = $data['tenancy_id'] ?? null;
        $data['gateway_reference'] = $data['gateway_reference'] ?? null;
        $data['paid_at'] = $data['paid_at'] ?? now();
        $data['status'] = 'unmatched';
        $data['reverses_payment_id'] = null;
        $data['recorded_by_user_id'] = auth()->id();
        $data['confirmed_by_user_id'] = null;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('payments')->insertGetId($data);
        $payment = DB::table('payments')->find($id);

        return 
            response()->json($payment, 201);
    }

    /**
     * Display the specified payment.
     */
    public function show(string $id)
    {
        $payment = DB::table('payments')->find($id);

        if (! $payment) {
            return response()->json(['message' => 'Payment not found.'], 404);
        }

        return 
            response()->json($payment);
    }

    /**
     * Update the specified payment in storage.
     */
    public function update(Request $request, string $id)
    {
        $payment = DB::table('payments')->find($id);

        if (! $payment) {
            return response()->json(['message' => 'Payment not found.'], 404);
        }

        $data = $request->validate([
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'tenancy_id' => ['nullable', 'integer', 'exists:tenancies,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(['cash', 'bank', 'mpesa', 'card'])],
            'gateway_reference' => ['nullable', 'string', 'max:100', 'unique:payments,gateway_reference,' . $id],
            'paid_at' => ['nullable', 'date'],
        ]);

        $data['tenant_id'] = $data['tenant_id'] ?? null;
        $data['tenancy_id'] = $data['tenancy_id'] ?? null;
        $data['gateway_reference'] = $data['gateway_reference'] ?? null;
        $data['paid_at'] = $data['paid_at'] ?? now();
        $data['updated_at'] = now();

        DB::table('payments')->where('id', $id)->update($data);
        $payment = DB::table('payments')->find($id);

        return 
            response()->json($payment);
            
    }

    /**
     * Remove the specified payment from storage.
     */
    public function destroy(string $id)
    {
         $payment = DB::table('payments')->find($id);

        if (! $payment) {
            return response()->json(['message' => 'Payment not found.'], 404);
        }

        DB::table('payments')->where('id', $id)->delete();

        return 
            response()->json(null, 204);
    }
}
