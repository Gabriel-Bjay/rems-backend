<?php

namespace App\Http\Controllers;

use App\Services\Access;
use App\Services\Billing;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function index(Request $request, Billing $billing)
    {
        // Keeps overdue statuses right on hosts without a scheduler running.
        $billing->markOverdue();

        $request->validate([
            'status' => ['nullable', Rule::in(['unpaid', 'partially_paid', 'paid', 'overdue', 'void'])],
            'tenancy_id' => ['nullable', 'integer'],
        ]);

        $invoices = $this->visible($request)
            ->when($request->query('status'), fn ($q, $status) => $q->where('invoices.status', $status))
            ->when($request->query('tenancy_id'), fn ($q, $id) => $q->where('invoices.tenancy_id', $id))
            ->get();

        return response()->json($invoices);
    }

    public function store(Request $request, Billing $billing)
    {
        $data = $request->validate([
            'tenancy_id' => ['required', 'integer', 'exists:tenancies,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after:period_start'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
        ]);

        $data['total_amount'] = 0;
        $data['status'] = 'unpaid';
        $data['void_reason'] = null;
        $data['replaced_by_invoice_id'] = null;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('invoices')->insertGetId($data);
        $billing->recalculateInvoiceStatus($id);

        return response()->json($this->visible($request)->where('invoices.id', $id)->first(), 201);
    }

    public function show(Request $request, string $id)
    {
        $invoice = $this->visible($request)->where('invoices.id', $id)->first();

        if (! $invoice) {
            return response()->json(['message' => 'Invoice not found.'], 404);
        }

        $invoice->items = DB::table('invoice_items')
            ->where('invoice_id', $id)
            ->orderBy('id')
            ->get(['id', 'description', 'amount', 'source']);

        $invoice->allocations = DB::table('payment_allocations')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->where('payment_allocations.invoice_id', $id)
            ->orderBy('payments.paid_at')
            ->get([
                'payment_allocations.id',
                'payment_allocations.payment_id',
                'payment_allocations.amount_applied',
                'payments.method',
                'payments.paid_at',
                'payments.gateway_reference',
            ]);

        return response()->json($invoice);
    }

    public function update(Request $request, string $id, Billing $billing)
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
        // A new due date can move an invoice into or out of overdue.
        $billing->recalculateInvoiceStatus((int) $id);

        return response()->json($this->visible($request)->where('invoices.id', $id)->first());
    }

    public function destroy(string $id)
    {
        $invoice = DB::table('invoices')->find($id);

        if (! $invoice) {
            return response()->json(['message' => 'Invoice not found.'], 404);
        }

        if (DB::table('payment_allocations')->where('invoice_id', $id)->exists()) {
            return response()->json([
                'message' => 'This invoice has payments applied to it. Void it instead.',
            ], 422);
        }

        DB::table('invoices')->where('id', $id)->delete();

        return response()->json(null, 204);
    }

    /** Cancel an invoice that was issued in error, keeping it on record. */
    public function void(Request $request, string $id)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $invoice = DB::table('invoices')->find($id);

        if (! $invoice) {
            return response()->json(['message' => 'Invoice not found.'], 404);
        }

        if ($invoice->status === 'void') {
            return response()->json(['message' => 'This invoice is already void.'], 422);
        }

        if (DB::table('payment_allocations')->where('invoice_id', $id)->exists()) {
            return response()->json([
                'message' => 'Remove the payments applied to this invoice before voiding it.',
            ], 422);
        }

        DB::table('invoices')->where('id', $id)->update([
            'status' => 'void',
            'void_reason' => $data['reason'],
            'updated_at' => now(),
        ]);

        return response()->json($this->visible($request)->where('invoices.id', $id)->first());
    }

    /** Issue this period's invoice for every active tenancy that does not have one yet. */
    public function generate(Billing $billing)
    {
        return response()->json(['created' => $billing->generateDueInvoices()]);
    }

    /** Invoices the user may see, with who and what they are for and how much is still owed. */
    private function visible(Request $request): Builder
    {
        $paid = '(SELECT COALESCE(SUM(amount_applied), 0) FROM payment_allocations
            WHERE payment_allocations.invoice_id = invoices.id)';

        $query = DB::table('invoices')
            ->join('tenancies', 'tenancies.id', '=', 'invoices.tenancy_id')
            ->join('tenants', 'tenants.id', '=', 'tenancies.tenant_id')
            ->join('units', 'units.id', '=', 'tenancies.unit_id')
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->select(
                'invoices.*',
                'tenancies.tenant_id',
                'tenants.fname as tenant_fname',
                'tenants.lname as tenant_lname',
                'units.name as unit_name',
                'properties.name as property_name',
                DB::raw("$paid as amount_paid"),
                DB::raw("CASE WHEN invoices.status = 'void' THEN 0 ELSE invoices.total_amount - $paid END as balance"),
            )
            ->orderByDesc('invoices.issue_date')
            ->orderByDesc('invoices.id');

        return Access::limit($query, 'invoices.tenancy_id', Access::for($request->user())->tenancyIds());
    }
}
