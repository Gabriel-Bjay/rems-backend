<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The rent cycle: invoices per billing period, payments applied to the
 * oldest open invoices, agent commission, overdue marking, and the tenant
 * notifications that go with each step.
 *
 * Billing periods follow the tenancy's start date (a tenancy that starts on
 * the 15th is billed 15th to 14th), so no first-month proration is needed.
 */
class Billing
{
    public const DUE_AFTER_DAYS = 5;
    public const OPEN_STATUSES = ['unpaid', 'partially_paid', 'overdue'];

    private const CYCLE_MONTHS = ['monthly' => 1, 'quarterly' => 3, 'annually' => 12];

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} start and end of the period containing $date */
    public function periodFor(object $tenancy, CarbonImmutable $date): array
    {
        $start = CarbonImmutable::parse($tenancy->start_date)->startOfDay();
        $months = self::CYCLE_MONTHS[$tenancy->billing_cycle] ?? 1;

        // Always offset from the original start date so month-end starts do
        // not drift (Jan 31 -> Feb 28 -> Mar 31, not Mar 28).
        $index = 0;
        while ($start->addMonthsNoOverflow(($index + 1) * $months)->lte($date)) {
            $index++;
        }

        return [
            $start->addMonthsNoOverflow($index * $months),
            $start->addMonthsNoOverflow(($index + 1) * $months)->subDay(),
        ];
    }

    /** Create the invoice for the period starting $periodStart; null if it already exists. */
    public function invoiceFor(object $tenancy, CarbonImmutable $periodStart, ?CarbonImmutable $today = null): ?int
    {
        $today ??= CarbonImmutable::today();
        [$start, $end] = $this->periodFor($tenancy, $periodStart);

        $exists = DB::table('invoices')
            ->where('tenancy_id', $tenancy->id)
            ->where('period_start', $start->toDateString())
            ->where('status', '!=', 'void')
            ->exists();
        if ($exists) {
            return null;
        }

        $months = self::CYCLE_MONTHS[$tenancy->billing_cycle] ?? 1;
        $unit = DB::table('units')->find($tenancy->unit_id);

        $items = collect([['description' => "Rent - {$unit->name}", 'amount' => $unit->base_rent]])
            ->merge(DB::table('unit_charges')->where('unit_id', $unit->id)->get(['name', 'amount'])
                ->map(fn ($c) => ['description' => $c->name, 'amount' => $c->amount]))
            ->merge(DB::table('tenancy_charges')->where('tenancy_id', $tenancy->id)->get(['name', 'amount'])
                ->map(fn ($c) => ['description' => $c->name, 'amount' => $c->amount]))
            ->map(fn ($item) => [
                'description' => $months > 1 ? "{$item['description']} ({$months} months)" : $item['description'],
                'amount' => round($item['amount'] * $months, 2),
            ]);

        $total = round($items->sum('amount'), 2);
        $issueDate = $start;
        $dueDate = $issueDate->addDays(self::DUE_AFTER_DAYS);

        $invoiceId = DB::transaction(function () use ($tenancy, $start, $end, $issueDate, $dueDate, $total, $items, $today) {
            $now = now();
            $id = DB::table('invoices')->insertGetId([
                'tenancy_id' => $tenancy->id,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'total_amount' => $total,
                'status' => $dueDate->lt($today) ? 'overdue' : 'unpaid',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('invoice_items')->insert($items->map(fn ($item) => $item + [
                'invoice_id' => $id,
                'source' => 'recurring',
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());

            return $id;
        });

        $this->notifyTenant(
            $tenancy->tenant_id,
            'invoice_issued',
            sprintf('Invoice for %s: %s due %s.', $start->format('M Y'), $this->money($total), $dueDate->format('j M')),
            "/app/invoices/{$invoiceId}",
        );

        return $invoiceId;
    }

    /** Issue the current period's invoice for every active tenancy that lacks one. */
    public function generateDueInvoices(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $created = 0;

        foreach (DB::table('tenancies')->where('status', 'active')->get() as $tenancy) {
            if ($today->lt(CarbonImmutable::parse($tenancy->start_date))) {
                continue;
            }
            [$start] = $this->periodFor($tenancy, $today);
            if ($tenancy->end_date && $start->gt(CarbonImmutable::parse($tenancy->end_date))) {
                continue;
            }
            if ($this->invoiceFor($tenancy, $start, $today)) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Apply a confirmed payment to the tenancy's (or tenant's) open invoices,
     * oldest due first, then record the agent's commission.
     *
     * @return array{allocated: float, unallocated: float, invoices: int}
     */
    public function applyPayment(int $paymentId, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();

        $result = DB::transaction(function () use ($paymentId, $today) {
            $payment = DB::table('payments')->lockForUpdate()->find($paymentId);
            $remaining = round($payment->amount - $this->allocatedOnPayment($paymentId), 2);

            $invoices = DB::table('invoices')
                ->whereIn('status', self::OPEN_STATUSES)
                ->where(function ($q) use ($payment) {
                    if ($payment->tenancy_id) {
                        $q->where('tenancy_id', $payment->tenancy_id);
                    } elseif ($payment->tenant_id) {
                        $q->whereIn('tenancy_id', DB::table('tenancies')
                            ->where('tenant_id', $payment->tenant_id)->select('id'));
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->orderBy('due_date')
                ->orderBy('id')
                ->get();

            $applied = 0.0;
            $touched = 0;
            foreach ($invoices as $invoice) {
                if ($remaining <= 0) {
                    break;
                }
                $balance = round($invoice->total_amount - $this->allocatedOnInvoice($invoice->id), 2);
                if ($balance <= 0) {
                    continue;
                }
                $amount = min($balance, $remaining);
                DB::table('payment_allocations')->insert([
                    'payment_id' => $paymentId,
                    'invoice_id' => $invoice->id,
                    'amount_applied' => $amount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->recalculateInvoiceStatus($invoice->id, $today);
                $remaining = round($remaining - $amount, 2);
                $applied = round($applied + $amount, 2);
                $touched++;
            }

            $this->recordCommission($payment, $invoices->first()?->tenancy_id);

            return ['allocated' => $applied, 'unallocated' => max($remaining, 0.0), 'invoices' => $touched];
        });

        $payment = DB::table('payments')->find($paymentId);
        $tenantId = $payment->tenant_id
            ?? DB::table('tenancies')->where('id', $payment->tenancy_id)->value('tenant_id');
        $this->notifyTenant(
            $tenantId,
            'payment_received',
            sprintf('Payment of %s received. Thank you.', $this->money($payment->amount)),
            '/app/payments',
        );

        return $result;
    }

    /** Paid, overdue, partially paid or unpaid, from what has been allocated and the due date. */
    public function recalculateInvoiceStatus(int $invoiceId, ?CarbonImmutable $today = null): void
    {
        $today ??= CarbonImmutable::today();
        $invoice = DB::table('invoices')->find($invoiceId);
        if (! $invoice || $invoice->status === 'void') {
            return;
        }

        $allocated = $this->allocatedOnInvoice($invoiceId);
        $pastDue = CarbonImmutable::parse($invoice->due_date)->lt($today);

        $status = match (true) {
            $allocated > 0 && $allocated >= (float) $invoice->total_amount => 'paid',
            $pastDue => 'overdue',
            $allocated > 0 => 'partially_paid',
            default => 'unpaid',
        };

        DB::table('invoices')->where('id', $invoiceId)->update([
            'status' => $status,
            'updated_at' => now(),
        ]);
    }

    /** Flag unpaid and partially paid invoices past their due date. */
    public function markOverdue(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $invoices = DB::table('invoices')
            ->join('tenancies', 'tenancies.id', '=', 'invoices.tenancy_id')
            ->whereIn('invoices.status', ['unpaid', 'partially_paid'])
            ->where('invoices.due_date', '<', $today->toDateString())
            ->get(['invoices.id', 'invoices.total_amount', 'invoices.period_start', 'tenancies.tenant_id']);

        foreach ($invoices as $invoice) {
            DB::table('invoices')->where('id', $invoice->id)->update([
                'status' => 'overdue',
                'updated_at' => now(),
            ]);
            $this->notifyTenant(
                $invoice->tenant_id,
                'invoice_overdue',
                sprintf('Your %s invoice is overdue. Balance: %s.',
                    CarbonImmutable::parse($invoice->period_start)->format('M Y'),
                    $this->money($invoice->total_amount - $this->allocatedOnInvoice($invoice->id))),
                "/app/invoices/{$invoice->id}",
            );
        }

        return $invoices->count();
    }

    /** Open the deposit record a newly activated tenancy has to pay (one month's rent). */
    public function openDeposit(object $tenancy): void
    {
        if (DB::table('deposits')->where('tenancy_id', $tenancy->id)->exists()) {
            return;
        }

        DB::table('deposits')->insert([
            'tenancy_id' => $tenancy->id,
            'amount_required' => DB::table('units')->where('id', $tenancy->unit_id)->value('base_rent'),
            'amount_held' => 0,
            'status' => 'pending',
            'deductions' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function allocatedOnInvoice(int $invoiceId): float
    {
        return round((float) DB::table('payment_allocations')->where('invoice_id', $invoiceId)->sum('amount_applied'), 2);
    }

    public function allocatedOnPayment(int $paymentId): float
    {
        return round((float) DB::table('payment_allocations')->where('payment_id', $paymentId)->sum('amount_applied'), 2);
    }

    public function notifyTenant(?int $tenantId, string $event, string $message, ?string $url = null): void
    {
        $userId = $tenantId ? DB::table('tenants')->where('id', $tenantId)->value('user_id') : null;
        if (! $userId) {
            return;
        }

        DB::table('notifications')->insert([
            'user_id' => $userId,
            'event_type' => $event,
            'message' => mb_substr($message, 0, 255),
            'related_url' => $url,
            'is_read' => false,
            'delivery_channel' => 'in_app',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function money(float|string $amount): string
    {
        return 'KES '.number_format((float) $amount, 2);
    }

    /** The unit's agent earns their commission rate on each confirmed payment. */
    private function recordCommission(object $payment, ?int $fallbackTenancyId): void
    {
        $tenancyId = $payment->tenancy_id ?? $fallbackTenancyId;
        if (! $tenancyId || DB::table('commissions')->where('payment_id', $payment->id)->exists()) {
            return;
        }

        $agent = DB::table('tenancies')
            ->join('units', 'units.id', '=', 'tenancies.unit_id')
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->join('agents', 'agents.id', '=', DB::raw('COALESCE(units.agent_id, properties.agent_id)'))
            ->where('tenancies.id', $tenancyId)
            ->first(['agents.id', 'agents.commission_rate']);

        if (! $agent || (float) $agent->commission_rate <= 0) {
            return;
        }

        DB::table('commissions')->insert([
            'agent_id' => $agent->id,
            'payment_id' => $payment->id,
            'rate' => $agent->commission_rate,
            'amount' => round($payment->amount * $agent->commission_rate / 100, 2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
