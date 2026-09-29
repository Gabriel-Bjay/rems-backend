<?php

namespace Tests\Feature;

use App\Services\Billing;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\BuildsPortfolio;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use BuildsPortfolio;
    use RefreshDatabase;

    private Billing $billing;
    private int $agentId;
    private int $tenantId;
    private int $unitId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRoles();
        $this->billing = app(Billing::class);

        $ownerId = $this->person('owners', ['status' => 'active']);
        $this->agentId = $this->person('agents', ['commission_rate' => 10]);
        $this->tenantId = $this->person('tenants');
        $propertyId = $this->property($ownerId, $this->agentId);
        $this->unitId = $this->unit($propertyId, 20000, ['Water' => 1000, 'Service charge' => 2000]);
    }

    public function test_billing_periods_follow_the_tenancy_start_date_without_drifting(): void
    {
        $tenancy = (object) ['start_date' => '2026-01-31', 'billing_cycle' => 'monthly'];

        [$start, $end] = $this->billing->periodFor($tenancy, CarbonImmutable::parse('2026-03-31'));
        $this->assertSame(['2026-03-31', '2026-04-29'], [$start->toDateString(), $end->toDateString()]);

        [$start] = $this->billing->periodFor($tenancy, CarbonImmutable::parse('2026-02-28'));
        $this->assertSame('2026-02-28', $start->toDateString());

        $quarterly = (object) ['start_date' => '2026-01-15', 'billing_cycle' => 'quarterly'];
        [$start, $end] = $this->billing->periodFor($quarterly, CarbonImmutable::parse('2026-05-01'));
        $this->assertSame(['2026-04-15', '2026-07-14'], [$start->toDateString(), $end->toDateString()]);
    }

    public function test_invoice_has_rent_and_recurring_charges_and_is_created_once(): void
    {
        $tenancy = $this->tenancy($this->unitId, $this->tenantId, '2026-09-10');
        $today = CarbonImmutable::parse('2026-09-12');

        $id = $this->billing->invoiceFor($tenancy, $today, $today);

        $invoice = DB::table('invoices')->find($id);
        $this->assertEquals(23000, $invoice->total_amount);
        $this->assertSame(['2026-09-10', '2026-10-09', '2026-09-15'],
            [$invoice->period_start, $invoice->period_end, $invoice->due_date]);
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame(3, DB::table('invoice_items')->where('invoice_id', $id)->count());
        $this->assertNull($this->billing->invoiceFor($tenancy, $today, $today));
    }

    public function test_quarterly_invoice_bills_three_months(): void
    {
        $tenancy = $this->tenancy($this->unitId, $this->tenantId, '2026-07-01', cycle: 'quarterly');
        $today = CarbonImmutable::parse('2026-07-02');

        $invoice = DB::table('invoices')->find($this->billing->invoiceFor($tenancy, $today, $today));

        $this->assertEquals(69000, $invoice->total_amount);
        $this->assertSame('2026-09-30', $invoice->period_end);
    }

    public function test_confirmed_payment_pays_oldest_invoices_first_and_earns_commission(): void
    {
        $tenancy = $this->tenancy($this->unitId, $this->tenantId, '2026-07-01');
        $today = CarbonImmutable::parse('2026-09-02');
        foreach (['2026-07-01', '2026-08-01', '2026-09-01'] as $period) {
            $this->billing->invoiceFor($tenancy, CarbonImmutable::parse($period), $today);
        }
        $paymentId = $this->payment($tenancy, 30000, 'completed');

        $result = $this->billing->applyPayment($paymentId, $today);

        $this->assertEquals(['allocated' => 30000.0, 'unallocated' => 0.0, 'invoices' => 2], $result);
        $statuses = DB::table('invoices')->where('tenancy_id', $tenancy->id)
            ->orderBy('period_start')->pluck('status')->all();
        // July fully paid; August partly paid but past due; September untouched and not yet due.
        $this->assertSame(['paid', 'overdue', 'unpaid'], $statuses);
        $this->assertDatabaseHas('commissions', [
            'agent_id' => $this->agentId, 'payment_id' => $paymentId, 'amount' => 3000,
        ]);
    }

    public function test_overpayment_leaves_the_rest_unallocated(): void
    {
        $tenancy = $this->tenancy($this->unitId, $this->tenantId, '2026-09-01');
        $today = CarbonImmutable::parse('2026-09-02');
        $this->billing->invoiceFor($tenancy, $today, $today);

        $result = $this->billing->applyPayment($this->payment($tenancy, 25000, 'completed'), $today);

        $this->assertEquals(['allocated' => 23000.0, 'unallocated' => 2000.0, 'invoices' => 1], $result);
    }

    public function test_mark_overdue_flags_past_due_invoices_and_notifies_the_tenant(): void
    {
        $user = $this->userWithRole('tenant', $this->tenantId);
        $tenancy = $this->tenancy($this->unitId, $this->tenantId, '2026-09-01');
        $this->billing->invoiceFor($tenancy, CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-01'));

        $this->assertSame(1, $this->billing->markOverdue(CarbonImmutable::parse('2026-09-10')));

        $this->assertSame('overdue', DB::table('invoices')->value('status'));
        $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'event_type' => 'invoice_overdue']);
        $this->assertSame(0, $this->billing->markOverdue(CarbonImmutable::parse('2026-09-11')));
    }

    public function test_activating_a_tenancy_opens_the_deposit_and_first_invoice(): void
    {
        $tenancy = $this->tenancy($this->unitId, $this->tenantId, now()->toDateString(), 'draft');
        Sanctum::actingAs($this->userWithRole('agent', $this->agentId));

        $this->postJson("/api/tenancies/{$tenancy->id}/activate")->assertOk();

        $this->assertDatabaseHas('units', ['id' => $this->unitId, 'status' => 'occupied']);
        $this->assertDatabaseHas('deposits', ['tenancy_id' => $tenancy->id, 'amount_required' => 20000, 'status' => 'pending']);
        $this->assertDatabaseHas('invoices', ['tenancy_id' => $tenancy->id, 'total_amount' => 23000]);
    }

    public function test_confirming_a_payment_over_the_api_applies_it(): void
    {
        $tenancy = $this->tenancy($this->unitId, $this->tenantId, now()->toDateString());
        $this->billing->invoiceFor($tenancy, CarbonImmutable::today());
        $paymentId = $this->payment($tenancy, 23000);
        Sanctum::actingAs($this->userWithRole('agent', $this->agentId));

        $this->postJson("/api/payments/$paymentId/confirm")
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('applied.allocated', 23000);

        $this->assertSame('paid', DB::table('invoices')->value('status'));
    }

    public function test_generate_endpoint_issues_missing_invoices_for_active_tenancies(): void
    {
        $this->tenancy($this->unitId, $this->tenantId, now()->subMonth()->toDateString());
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->postJson('/api/invoices/generate')->assertOk()->assertJson(['created' => 1]);
        $this->postJson('/api/invoices/generate')->assertOk()->assertJson(['created' => 0]);
    }

    public function test_creating_an_invoice_uses_the_issue_date_column(): void
    {
        $tenancy = $this->tenancy($this->unitId, $this->tenantId, '2026-09-01');
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->postJson('/api/invoices', [
            'tenancy_id' => $tenancy->id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'issue_date' => '2026-09-01',
            'due_date' => '2026-09-05',
        ])->assertCreated()->assertJsonPath('issue_date', '2026-09-01');
    }

    public function test_an_invoice_with_payments_cannot_be_voided_or_deleted(): void
    {
        $tenancy = $this->tenancy($this->unitId, $this->tenantId, now()->toDateString());
        $invoiceId = $this->billing->invoiceFor($tenancy, CarbonImmutable::today());
        $this->billing->applyPayment($this->payment($tenancy, 5000, 'completed'));
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->postJson("/api/invoices/$invoiceId/void", ['reason' => 'Issued twice'])->assertStatus(422);
        $this->deleteJson("/api/invoices/$invoiceId")->assertStatus(422);
    }
}
