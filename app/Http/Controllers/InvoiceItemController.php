<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvoiceItemController extends Controller
{
    public function index(){
        $invoice_items = DB::table('invoice_items')->orderBy('id')->get();

        return 
            response()->json($invoice_items);
    }
    public function store(Request $request)
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'description' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0'],
            'source' => ['nullable', Rule::in(['recurring', 'one_off'])],
        ]);

        $data['source'] = $data['source'] ?? 'recurring';
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $invoiceItem = DB::transaction(function () use ($data) {
            $id = DB::table('invoice_items')->insertGetId($data);

            $this->recalculateInvoiceTotal($data['invoice_id']);

            return DB::table('invoice_items')->find($id);
        });

        return response()->json($invoiceItem, 201);
    }
    public function show(string $id)
    {
        $invoice_item = DB::table('invoice_items')->find($id);

        if (! $invoice_item) {
            return response()->json(['message' => 'Invoice item not found.'], 404);
        }

        return response()->json($invoice_item);
    }
    public function update(Request $request, string $id)
    {
        $invoice_item = DB::table('invoice_items')->find($id);

        if (! $invoice_item) {
            return response()->json(['message' => 'Invoice item not found.'], 404);
        }

        $data = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'description' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0'],
            'source' => ['nullable', Rule::in(['recurring', 'one_off'])],
        ]);

        $data['source'] = $data['source'] ?? 'recurring';
        $data['updated_at'] = now();

        $oldInvoiceId = $invoice_item->invoice_id;

        $updatedItem = DB::transaction(function () use ($data, $id, $oldInvoiceId) {
            DB::table('invoice_items')->where('id', $id)->update($data);

            $this->recalculateInvoiceTotal($data['invoice_id']);

            if ($oldInvoiceId !== $data['invoice_id']) {
                $this->recalculateInvoiceTotal($oldInvoiceId);
            }

            return DB::table('invoice_items')->find($id);
        });

        return response()->json($updatedItem);
    }

    private function recalculateInvoiceTotal(int $invoiceId): void
    {
        $newTotal = DB::table('invoice_items')
            ->where('invoice_id', $invoiceId)
            ->sum('amount');

        DB::table('invoices')
            ->where('id', $invoiceId)
            ->update([
                'total_amount' => $newTotal,
                'updated_at' => now(),
            ]);
    }
    public function destroy(string $id)
    {
        $invoice_item = DB::table('invoice_items')->find($id);

        if (! $invoice_item) {
            return response()->json(['message' => 'Invoice item not found.'], 404);
        }

        DB::transaction(function () use ($invoice_item) {
            DB::table('invoice_items')->where('id', $invoice_item->id)->delete();

            $this->recalculateInvoiceTotal($invoice_item->invoice_id);
        });

        return response()->json(null, 204);
    }
}
