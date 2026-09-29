<?php

namespace App\Http\Controllers;

use App\Services\Access;
use App\Services\Billing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    /**
     * Display a listing of payments.
     */
    public function index(Request $request)
    {
        return response()->json($this->visible($request)->get(), 200);
    }

    /**
     * Store a newly created payment in storage.
     *
     * Staff record any payment; a tenant can only report their own, which
     * stays unmatched until staff confirm it.
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

        $access = Access::for($request->user());
        if (! $access->is('admin', 'agent')) {
            $tenantId = $access->tenantId();
            if (! $tenantId) {
                return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
            }
            if (! empty($data['tenancy_id']) && ! in_array((int) $data['tenancy_id'], $access->tenancyIds(), true)) {
                return response()->json(['message' => 'That tenancy is not yours.'], 422);
            }
            $data['tenant_id'] = $tenantId;
        }

        $data['tenancy_id'] = $data['tenancy_id'] ?? null;
        $data['tenant_id'] = $data['tenant_id']
            ?? ($data['tenancy_id'] ? DB::table('tenancies')->where('id', $data['tenancy_id'])->value('tenant_id') : null);
        $data['gateway_reference'] = $data['gateway_reference'] ?? null;
        $data['paid_at'] = $this->timestamp($data['paid_at'] ?? null);
        $data['status'] = 'unmatched';
        $data['reverses_payment_id'] = null;
        $data['recorded_by_user_id'] = $request->user()->id;
        $data['confirmed_by_user_id'] = null;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('payments')->insertGetId($data);

        return response()->json($this->visible($request)->where('payments.id', $id)->first(), 201);
    }

    /**
     * Display the specified payment.
     */
    public function show(Request $request, string $id)
    {
        $payment = $this->visible($request)->where('payments.id', $id)->first();

        if (! $payment) {
            return response()->json(['message' => 'Payment not found.'], 404);
        }

        $payment->allocations = DB::table('payment_allocations')
            ->join('invoices', 'invoices.id', '=', 'payment_allocations.invoice_id')
            ->where('payment_allocations.payment_id', $id)
            ->orderBy('invoices.due_date')
            ->get([
                'payment_allocations.id',
                'payment_allocations.invoice_id',
                'payment_allocations.amount_applied',
                'invoices.period_start',
                'invoices.period_end',
                'invoices.status as invoice_status',
            ]);

        return response()->json($payment);
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
        $data['paid_at'] = $this->timestamp($data['paid_at'] ?? null);
        $data['updated_at'] = now();

        DB::table('payments')->where('id', $id)->update($data);

        return response()->json($this->visible($request)->where('payments.id', $id)->first());
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

        return response()->json(null, 204);
    }

    /**
     * Confirm the specified payment and apply it to the oldest open invoices.
     */
    public function confirm(Request $request, string $id, Billing $billing)
    {
        $payment = DB::table('payments')->find($id);

        if (! $payment) {
            return response()->json(['message' => 'Payment not found.'], 404);
        }

        if ($payment->status !== 'unmatched') {
            return response()->json([
                'message' => 'Only an unmatched payment can be confirmed.',
            ], 422);
        }

        DB::table('payments')->where('id', $id)->update([
            'status' => 'completed',
            'confirmed_by_user_id' => $request->user()->id,
            'updated_at' => now(),
        ]);

        $applied = $billing->applyPayment((int) $id);

        $payment = $this->visible($request)->where('payments.id', $id)->first();
        $payment->applied = $applied;

        return response()->json($payment);
    }

    /** Browsers send ISO strings with an offset; store every paid_at as a plain UTC timestamp. */
    private function timestamp(?string $value): string
    {
        return ($value ? CarbonImmutable::parse($value) : CarbonImmutable::now())->utc()->toDateTimeString();
    }

    /** Payments the user may see, with the tenant, unit and amount allocated. */
    private function visible(Request $request): Builder
    {
        $access = Access::for($request->user());

        $query = DB::table('payments')
            ->leftJoin('tenancies', 'tenancies.id', '=', 'payments.tenancy_id')
            ->leftJoin('tenants', 'tenants.id', '=', DB::raw('COALESCE(payments.tenant_id, tenancies.tenant_id)'))
            ->leftJoin('units', 'units.id', '=', 'tenancies.unit_id')
            ->select(
                'payments.*',
                'tenants.fname as tenant_fname',
                'tenants.lname as tenant_lname',
                'units.name as unit_name',
                DB::raw('(SELECT COALESCE(SUM(amount_applied), 0) FROM payment_allocations
                    WHERE payment_allocations.payment_id = payments.id) as allocated'),
            )
            ->orderByDesc('payments.paid_at')
            ->orderByDesc('payments.id');

        if (! $access->isAdmin()) {
            $query->where(fn ($q) => $q
                ->whereIn('payments.tenancy_id', $access->tenancyIds())
                ->orWhereIn('payments.tenant_id', $access->tenantIds()));
        }

        return $query;
    }
}
