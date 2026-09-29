<?php

namespace Tests\Feature;

use App\Services\Billing;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\BuildsPortfolio;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use BuildsPortfolio;
    use RefreshDatabase;

    private int $ownerA;
    private int $ownerB;
    private int $tenantA;
    private int $tenantB;
    private object $tenancyA;
    private object $tenancyB;
    private int $invoiceA;
    private int $invoiceB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRoles();

        $this->ownerA = $this->person('owners', ['status' => 'active']);
        $this->ownerB = $this->person('owners', ['status' => 'active']);
        $this->tenantA = $this->person('tenants');
        $this->tenantB = $this->person('tenants');

        $unitA = $this->unit($this->property($this->ownerA), 20000, [], 'occupied');
        $unitB = $this->unit($this->property($this->ownerB), 30000, [], 'occupied');
        $this->tenancyA = $this->tenancy($unitA, $this->tenantA, now()->toDateString());
        $this->tenancyB = $this->tenancy($unitB, $this->tenantB, now()->toDateString());

        $billing = app(Billing::class);
        $this->invoiceA = $billing->invoiceFor($this->tenancyA, CarbonImmutable::today());
        $this->invoiceB = $billing->invoiceFor($this->tenancyB, CarbonImmutable::today());
    }

    public function test_tenant_sees_only_their_own_invoices(): void
    {
        Sanctum::actingAs($this->userWithRole('tenant', $this->tenantA));

        $this->getJson('/api/invoices')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.id', $this->invoiceA);
        $this->getJson("/api/invoices/{$this->invoiceA}")->assertOk()->assertJsonCount(1, 'items');
        $this->getJson("/api/invoices/{$this->invoiceB}")->assertNotFound();
    }

    public function test_tenant_cannot_use_staff_or_admin_endpoints(): void
    {
        Sanctum::actingAs($this->userWithRole('tenant', $this->tenantA));

        $this->getJson('/api/owners')->assertForbidden();
        $this->getJson('/api/tenants')->assertForbidden();
        $this->postJson('/api/invoices/generate')->assertForbidden();
        $this->postJson('/api/properties', ['owner_id' => $this->ownerA, 'name' => 'X', 'address' => 'Y'])
            ->assertForbidden();
    }

    public function test_tenant_can_report_their_own_payment_but_not_for_someone_else(): void
    {
        Sanctum::actingAs($this->userWithRole('tenant', $this->tenantA));

        $this->postJson('/api/payments', [
            'tenant_id' => $this->tenantB,
            'tenancy_id' => $this->tenancyA->id,
            'amount' => 20000,
            'method' => 'mpesa',
            'gateway_reference' => 'QK7X2ZP4TA',
        ])->assertCreated()
            ->assertJsonPath('tenant_id', $this->tenantA)
            ->assertJsonPath('status', 'unmatched');

        $this->postJson('/api/payments', [
            'tenancy_id' => $this->tenancyB->id,
            'amount' => 1000,
            'method' => 'mpesa',
        ])->assertStatus(422);

        $this->postJson('/api/payments/1/confirm')->assertForbidden();
    }

    public function test_tenant_can_raise_maintenance_only_for_the_unit_they_rent(): void
    {
        Sanctum::actingAs($this->userWithRole('tenant', $this->tenantA));

        $this->postJson('/api/maintenance-tickets', ['unit_id' => $this->tenancyA->unit_id, 'title' => 'Leaking tap'])
            ->assertCreated()
            ->assertJsonPath('raised_by_tenant_id', $this->tenantA)
            ->assertJsonPath('tenancy_id', $this->tenancyA->id);

        $this->postJson('/api/maintenance-tickets', ['unit_id' => $this->tenancyB->unit_id, 'title' => 'Not mine'])
            ->assertStatus(422);
    }

    public function test_owner_sees_only_their_properties_and_tenancies(): void
    {
        Sanctum::actingAs($this->userWithRole('owner', $this->ownerA));

        $this->getJson('/api/properties')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.owner_id', $this->ownerA);
        $this->getJson('/api/tenancies')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.id', $this->tenancyA->id);
        $this->getJson("/api/tenancies/{$this->tenancyB->id}")->assertNotFound();
    }

    public function test_agent_sees_only_what_they_manage(): void
    {
        $agent = $this->person('agents', ['commission_rate' => 5]);
        DB::table('properties')->where('owner_id', $this->ownerB)->update(['agent_id' => $agent]);
        Sanctum::actingAs($this->userWithRole('agent', $agent));

        $this->getJson('/api/invoices')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.id', $this->invoiceB);
    }

    public function test_admin_sees_everything(): void
    {
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->getJson('/api/invoices')->assertOk()->assertJsonCount(2);
        $this->getJson('/api/owners')->assertOk()->assertJsonCount(2);
    }
}
