<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentAllocationController extends Controller
{
    public function index()
    {
        $allocations = DB::table('payment_allocations')->orderByDesc('id')->get();

        return response()->json($allocations, 200);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'payment_id' => ['required', 'integer', 'exists:payments,id'],
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'amount_applied' => ['required', 'numeric', 'min:0.01'],
        ]);

        $error = $this->guardAllocation($data['payment_id'], $data['invoice_id'], $data['amount_applied'], null);

        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::transaction(function () use ($data) {
            $newId = DB::table('payment_allocations')->insertGetId($data);

            $this->recalculateInvoiceStatus($data['invoice_id']);

            return $newId;
        });

        $allocation = DB::table('payment_allocations')->find($id);

        return response()->json($allocation, 201);
    }

    public function show(string $id)
    {
        $allocation = DB::table('payment_allocations')->find($id);

        if (! $allocation) {
            return response()->json(['message' => 'Allocation not found.'], 404);
        }

        return response()->json($allocation);
    }

    public function update(Request $request, string $id)
    {
        $allocation = DB::table('payment_allocations')->find($id);

        if (! $allocation) {
            return response()->json(['message' => 'Allocation not found.'], 404);
        }

        $data = $request->validate([
            'payment_id' => ['required', 'integer', 'exists:payments,id'],
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'amount_applied' => ['required', 'numeric', 'min:0.01'],
        ]);

        $error = $this->guardAllocation($data['payment_id'], $data['invoice_id'], $data['amount_applied'], $id);

        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        $data['updated_at'] = now();

        $oldInvoiceId = $allocation->invoice_id;

        DB::transaction(function () use ($data, $id, $oldInvoiceId) {
            DB::table('payment_allocations')->where('id', $id)->update($data);

            $this->recalculateInvoiceStatus($data['invoice_id']);

            if ($oldInvoiceId != $data['invoice_id']) {
                $this->recalculateInvoiceStatus($oldInvoiceId);
            }
        });

        $allocation = DB::table('payment_allocations')->find($id);

        return response()->json($allocation);
    }

    public function destroy(string $id)
    {
        $allocation = DB::table('payment_allocations')->find($id);

        if (! $allocation) {
            return response()->json(['message' => 'Allocation not found.'], 404);
        }

        $invoiceId = $allocation->invoice_id;

        DB::transaction(function () use ($id, $invoiceId) {
            DB::table('payment_allocations')->where('id', $id)->delete();

            $this->recalculateInvoiceStatus($invoiceId);
        });

        return response()->json(null, 204);
    }

    private function guardAllocation($paymentId, $invoiceId, $amountApplied, $excludeId)
    {
        $payment = DB::table('payments')->find($paymentId);
        $invoice = DB::table('invoices')->find($invoiceId);

        if ($payment->status !== 'completed') {
            return 'Only a confirmed (completed) payment can be allocated.';
        }

        if ($invoice->status === 'void') {
            return 'You cannot allocate to a void invoice.';
        }

        $duplicate = DB::table('payment_allocations')
            ->where('payment_id', $paymentId)
            ->where('invoice_id', $invoiceId)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();

        if ($duplicate) {
            return 'This payment is already allocated to that invoice.';
        }

        $alreadyOnPayment = DB::table('payment_allocations')
            ->where('payment_id', $paymentId)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->sum('amount_applied');

        if ($alreadyOnPayment + $amountApplied > $payment->amount) {
            return 'That amount exceeds the unallocated balance of this payment.';
        }

        $alreadyOnInvoice = DB::table('payment_allocations')
            ->where('invoice_id', $invoiceId)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->sum('amount_applied');

        if ($alreadyOnInvoice + $amountApplied > $invoice->total_amount) {
            return 'That amount exceeds the outstanding balance of this invoice.';
        }

        return null;
    }

    private function recalculateInvoiceStatus($invoiceId)
    {
        $invoice = DB::table('invoices')->find($invoiceId);

        if (! $invoice) {
            return;
        }

        if ($invoice->status === 'void') {
            return;
        }

        $allocated = DB::table('payment_allocations')
            ->where('invoice_id', $invoiceId)
            ->sum('amount_applied');

        if ($allocated <= 0) {
            $status = 'unpaid';
        } elseif ($allocated < $invoice->total_amount) {
            $status = 'partially_paid';
        } else {
            $status = 'paid';
        }

        DB::table('invoices')->where('id', $invoiceId)->update([
            'status' => $status,
            'updated_at' => now(),
        ]);
    }
}