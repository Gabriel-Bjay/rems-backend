<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index(){
        $invoices = DB::table('invoices')->orderBy('id')->get();

        return 
            response()->json($invoices);
    }
    public function store(Request $request){
        $data = $request->validate([
            'tenancy_id' => ['required', 'integer', 'exists:tenancies,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after:period_start'],
            'issued_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issued_date'],
        ]);

        $data['total_amount'] = 0;
        $data['status'] = 'unpaid';
        $data['void_reason'] = null;
        $data['replaced_by_invoice_id'] = null;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('invoices')->insertGetId($data);
        $invoice = DB::table('invoices')->find($id);

        return 
            response()->json($invoice, 201);
    }
    public function show(string $id)
    {
        $invoice = DB::table('invoices')->find($id);

        if (! $invoice) {
            return response()->json(['message' => 'Invoice not found.'], 404);
        }

        return response()->json($invoice);
    }
    public function update(Request $request, string $id)
    {
        $invoice = DB::table('invoices')->find($id);

        if (! $invoice) {
            return response()->json(['message' => 'Invoice not found.'], 404);
        }

        $data = $request->validate([
            'tenancy_id' => ['required', 'integer', 'exists:tenancies,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after:period_start'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
        ]);

        $data['updated_at'] = now();

        DB::table('invoices')->where('id', $id)->update($data);
        $invoice = DB::table('invoices')->find($id);

        return response()->json($invoice);
    }
    public function destroy(string $id)
    {
        $invoice = DB::table('invoices')->find($id);

        if (! $invoice) {
            return response()->json(['message' => 'Invoice not found.'], 404);
        }

        DB::table('invoices')->where('id', $id)->delete();

        return response()->json(null, 204);
    }
}
