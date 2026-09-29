<?php

namespace App\Http\Controllers;

use App\Services\Access;
use App\Services\Billing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * One call for the dashboard, scoped to what the signed-in user may see:
 * admins get the whole portfolio, owners their properties, agents what
 * they manage, and tenants their own tenancy.
 */
class DashboardController extends Controller
{
    private const TREND_MONTHS = 6;
    private const EXPIRY_WINDOW_DAYS = 60;

    public function summary(Request $request, Billing $billing)
    {
        // Keeps overdue figures right on hosts without a scheduler running.
        $billing->markOverdue();

        $access = Access::for($request->user());
        $today = CarbonImmutable::today();
        $monthStart = $today->startOfMonth();
        $monthEnd = $today->endOfMonth();

        $billed = $this->billedBetween($access, $monthStart, $monthEnd);
        $collected = $this->collectedBetween($access, $monthStart, $monthEnd);

        return response()->json([
            'view' => $this->primaryRole($access),
            'generated_at' => now()->toIso8601String(),
            'portfolio' => $this->portfolio($access),
            'finance' => [
                'billed_this_month' => $billed,
                'collected_this_month' => $collected,
                'collection_rate' => $billed > 0 ? round($collected / $billed, 4) : null,
                'outstanding' => round((float) $this->openInvoices($access)->sum(DB::raw($this->balanceSql())), 2),
                'overdue_count' => $this->openInvoices($access)->where('invoices.status', 'overdue')->count(),
                'overdue_amount' => round((float) $this->openInvoices($access)
                    ->where('invoices.status', 'overdue')->sum(DB::raw($this->balanceSql())), 2),
                'pending_confirmations' => $this->payments($access)->where('payments.status', 'unmatched')->count(),
            ],
            'trend' => $this->trend($access, $today),
            'expiring_leases' => $this->expiringLeases($access, $today),
            'arrears' => $this->arrears($access),
            'recent_payments' => $this->payments($access)
                ->leftJoin('tenancies', 'tenancies.id', '=', 'payments.tenancy_id')
                ->leftJoin('tenants', 'tenants.id', '=', DB::raw('COALESCE(payments.tenant_id, tenancies.tenant_id)'))
                ->leftJoin('units', 'units.id', '=', 'tenancies.unit_id')
                ->orderByDesc('payments.paid_at')
                ->orderByDesc('payments.id')
                ->limit(6)
                ->get([
                    'payments.id', 'payments.amount', 'payments.method', 'payments.status',
                    'payments.paid_at', 'payments.gateway_reference',
                    'tenants.fname as tenant_fname', 'tenants.lname as tenant_lname',
                    'units.name as unit_name',
                ]),
            'maintenance' => $this->maintenance($access),
            'tenant' => $access->tenantId() ? $this->tenantView($access) : null,
        ]);
    }

    private function primaryRole(Access $access): string
    {
        foreach (['admin', 'agent', 'owner', 'tenant'] as $role) {
            if ($access->is($role)) {
                return $role;
            }
        }

        return 'none';
    }

    private function portfolio(Access $access): array
    {
        $units = Access::limit(DB::table('units'), 'units.id', $access->unitIds());
        $counts = (clone $units)
            ->select('status', DB::raw('COUNT(*) as n'))
            ->groupBy('status')
            ->pluck('n', 'status');

        $total = (int) $counts->sum();
        $occupied = (int) ($counts['occupied'] ?? 0);

        return [
            'properties' => Access::limit(DB::table('properties'), 'properties.id', $access->propertyIds())->count(),
            'units' => $total,
            'occupied' => $occupied,
            'vacant' => (int) ($counts['vacant'] ?? 0),
            'under_maintenance' => (int) ($counts['under_maintenance'] ?? 0),
            'occupancy_rate' => $total > 0 ? round($occupied / $total, 4) : null,
            'active_tenancies' => Access::limit(DB::table('tenancies'), 'tenancies.id', $access->tenancyIds())
                ->where('status', 'active')->count(),
            'rent_roll' => round((float) (clone $units)->where('status', 'occupied')->sum('base_rent'), 2),
        ];
    }

    private function trend(Access $access, CarbonImmutable $today): array
    {
        return collect(range(self::TREND_MONTHS - 1, 0))
            ->map(function (int $monthsAgo) use ($access, $today) {
                $start = $today->startOfMonth()->subMonthsNoOverflow($monthsAgo);
                $end = $start->endOfMonth();

                return [
                    'month' => $start->format('Y-m'),
                    'label' => $start->format('M'),
                    'billed' => $this->billedBetween($access, $start, $end),
                    'collected' => $this->collectedBetween($access, $start, $end),
                ];
            })
            ->all();
    }

    private function expiringLeases(Access $access, CarbonImmutable $today): array
    {
        return Access::limit(DB::table('tenancies'), 'tenancies.id', $access->tenancyIds())
            ->join('tenants', 'tenants.id', '=', 'tenancies.tenant_id')
            ->join('units', 'units.id', '=', 'tenancies.unit_id')
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->where('tenancies.status', 'active')
            ->whereNotNull('tenancies.end_date')
            ->whereBetween('tenancies.end_date', [
                $today->toDateString(),
                $today->addDays(self::EXPIRY_WINDOW_DAYS)->toDateString(),
            ])
            ->orderBy('tenancies.end_date')
            ->limit(5)
            ->get([
                'tenancies.id', 'tenancies.end_date',
                'tenants.fname as tenant_fname', 'tenants.lname as tenant_lname',
                'units.name as unit_name', 'properties.name as property_name',
            ])
            ->map(function ($lease) use ($today) {
                $lease->days_left = (int) $today->diffInDays(CarbonImmutable::parse($lease->end_date));

                return $lease;
            })
            ->all();
    }

    private function arrears(Access $access): array
    {
        return $this->openInvoices($access)
            ->join('tenants', 'tenants.id', '=', 'tenancies.tenant_id')
            ->join('units', 'units.id', '=', 'tenancies.unit_id')
            ->groupBy('tenancies.id', 'tenants.id', 'tenants.fname', 'tenants.lname', 'units.name')
            ->orderByDesc('balance')
            ->limit(5)
            ->get([
                'tenancies.id as tenancy_id',
                'tenants.id as tenant_id',
                'tenants.fname as tenant_fname',
                'tenants.lname as tenant_lname',
                'units.name as unit_name',
                DB::raw('SUM('.$this->balanceSql().') as balance'),
                DB::raw('MIN(invoices.due_date) as oldest_due'),
                DB::raw('COUNT(*) as invoices'),
            ])
            ->filter(fn ($row) => (float) $row->balance > 0)
            ->values()
            ->all();
    }

    private function maintenance(Access $access): array
    {
        $tickets = DB::table('maintenance_tickets')
            ->join('units', 'units.id', '=', 'maintenance_tickets.unit_id');

        if (! $access->isAdmin()) {
            $tickets->where(function ($q) use ($access) {
                $q->whereIn('maintenance_tickets.unit_id', $access->managedUnitIds());
                if ($access->tenantId()) {
                    $q->orWhere('maintenance_tickets.raised_by_tenant_id', $access->tenantId());
                }
            });
        }

        $counts = (clone $tickets)
            ->select('maintenance_tickets.status', DB::raw('COUNT(*) as n'))
            ->groupBy('maintenance_tickets.status')
            ->pluck('n', 'status');

        return [
            'open' => (int) ($counts['open'] ?? 0),
            'in_progress' => (int) ($counts['in_progress'] ?? 0),
            'resolved' => (int) ($counts['resolved'] ?? 0),
            'recent' => (clone $tickets)
                ->whereIn('maintenance_tickets.status', ['open', 'in_progress'])
                ->orderByDesc('maintenance_tickets.created_at')
                ->limit(5)
                ->get([
                    'maintenance_tickets.id', 'maintenance_tickets.title', 'maintenance_tickets.status',
                    'maintenance_tickets.created_at', 'units.name as unit_name',
                ]),
        ];
    }

    private function tenantView(Access $access): array
    {
        $own = DB::table('tenancies')->where('tenant_id', $access->tenantId());
        $tenancy = (clone $own)
            ->join('units', 'units.id', '=', 'tenancies.unit_id')
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->orderByRaw("CASE WHEN tenancies.status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('tenancies.start_date')
            ->first([
                'tenancies.id', 'tenancies.status', 'tenancies.start_date', 'tenancies.end_date',
                'tenancies.billing_cycle', 'units.id as unit_id', 'units.name as unit_name',
                'units.base_rent', 'properties.name as property_name', 'properties.address',
            ]);

        $open = DB::table('invoices')
            ->whereIn('invoices.status', Billing::OPEN_STATUSES)
            ->whereIn('invoices.tenancy_id', (clone $own)->pluck('id'));

        return [
            'tenancy' => $tenancy,
            'balance' => round((float) (clone $open)->sum(DB::raw($this->balanceSql())), 2),
            'next_due' => (clone $open)
                ->orderBy('invoices.due_date')
                ->first([
                    'invoices.id', 'invoices.due_date', 'invoices.period_start', 'invoices.status',
                    DB::raw($this->balanceSql().' as balance'),
                ]),
            'deposit' => $tenancy
                ? DB::table('deposits')->where('tenancy_id', $tenancy->id)
                    ->first(['amount_required', 'amount_held', 'status'])
                : null,
        ];
    }

    /** Open invoices (unpaid, partially paid, overdue) the user may see, joined to their tenancy. */
    private function openInvoices(Access $access): Builder
    {
        return Access::limit(
            DB::table('invoices')
                ->join('tenancies', 'tenancies.id', '=', 'invoices.tenancy_id')
                ->whereIn('invoices.status', Billing::OPEN_STATUSES),
            'invoices.tenancy_id',
            $access->tenancyIds(),
        );
    }

    private function payments(Access $access): Builder
    {
        $query = DB::table('payments');

        if (! $access->isAdmin()) {
            $query->where(fn ($q) => $q
                ->whereIn('payments.tenancy_id', $access->tenancyIds())
                ->orWhereIn('payments.tenant_id', $access->tenantIds()));
        }

        return $query;
    }

    private function billedBetween(Access $access, CarbonImmutable $start, CarbonImmutable $end): float
    {
        return round((float) Access::limit(DB::table('invoices'), 'invoices.tenancy_id', $access->tenancyIds())
            ->where('invoices.status', '!=', 'void')
            ->whereBetween('invoices.issue_date', [$start->toDateString(), $end->toDateString()])
            ->sum('invoices.total_amount'), 2);
    }

    private function collectedBetween(Access $access, CarbonImmutable $start, CarbonImmutable $end): float
    {
        return round((float) $this->payments($access)
            ->where('payments.status', 'completed')
            ->whereBetween('payments.paid_at', [$start->startOfDay(), $end->endOfDay()])
            ->sum('payments.amount'), 2);
    }

    private function balanceSql(): string
    {
        return 'invoices.total_amount - COALESCE((SELECT SUM(amount_applied) FROM payment_allocations
            WHERE payment_allocations.invoice_id = invoices.id), 0)';
    }
}
