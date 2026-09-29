<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\BuildsPortfolio;
use Tests\TestCase;

/** Day-to-day staff actions: maintenance tickets, ending leases and recording payments. */
class OperationsTest extends TestCase
{
    use BuildsPortfolio;
    use RefreshDatabase;

    private int $agentA;
    private int $agentB;
    private int $unitA;
    private int $unitB;
    private object $tenancyA;
    private object $tenancyB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRoles();

        $this->agentA = $this->person('agents', ['commission_rate' => 8]);
        $this->agentB = $this->person('agents', ['commission_rate' => 10]);
        $owner = $this->person('owners', ['status' => 'active']);

        // Agent A manages property A; property B belongs to agent B.
        $this->unitA = $this->unit($this->property($owner, $this->agentA), 20000, [], 'occupied');
        $this->unitB = $this->unit($this->property($owner, $this->agentB), 30000, [], 'occupied');
        $this->tenancyA = $this->tenancy($this->unitA, $this->person('tenants'), now()->subMonths(3)->toDateString());
        $this->tenancyB = $this->tenancy($this->unitB, $this->person('tenants'), now()->subMonths(3)->toDateString());
    }

    public function test_agent_picks_up_a_ticket_themselves_whatever_agent_id_is_sent(): void
    {
        $ticket = $this->ticket($this->unitA);
        Sanctum::actingAs($this->userWithRole('agent', $this->agentA));

        $this->postJson("/api/maintenance-tickets/{$ticket}/assign", ['agent_id' => $this->agentB])
            ->assertOk()
            ->assertJsonPath('status', 'in_progress')
            ->assertJsonPath('assigned_to_agent_id', $this->agentA);
    }

    public function test_agent_cannot_assign_or_resolve_tickets_outside_their_portfolio(): void
    {
        $ticket = $this->ticket($this->unitB);
        Sanctum::actingAs($this->userWithRole('agent', $this->agentA));

        $this->postJson("/api/maintenance-tickets/{$ticket}/assign")->assertNotFound();
        $this->postJson("/api/maintenance-tickets/{$ticket}/resolve", ['repair_cost' => 1500])->assertNotFound();
        $this->assertSame('open', DB::table('maintenance_tickets')->where('id', $ticket)->value('status'));
    }

    public function test_admin_must_name_the_agent_when_assigning(): void
    {
        $ticket = $this->ticket($this->unitB);
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->postJson("/api/maintenance-tickets/{$ticket}/assign")->assertStatus(422);
        $this->postJson("/api/maintenance-tickets/{$ticket}/assign", ['agent_id' => $this->agentB])
            ->assertOk()
            ->assertJsonPath('assigned_to_agent_id', $this->agentB);
    }

    public function test_agent_cannot_end_a_tenancy_outside_their_portfolio(): void
    {
        Sanctum::actingAs($this->userWithRole('agent', $this->agentA));

        $this->postJson("/api/tenancies/{$this->tenancyB->id}/end")->assertNotFound();
        $this->assertSame('active', DB::table('tenancies')->where('id', $this->tenancyB->id)->value('status'));
    }

    public function test_ending_a_lease_early_moves_the_end_date_to_today_and_frees_the_unit(): void
    {
        DB::table('tenancies')->where('id', $this->tenancyA->id)->update(['end_date' => now()->addYear()->toDateString()]);
        Sanctum::actingAs($this->userWithRole('agent', $this->agentA));

        $this->postJson("/api/tenancies/{$this->tenancyA->id}/end")
            ->assertOk()
            ->assertJsonPath('status', 'ended')
            ->assertJsonPath('end_date', now()->toDateString());
        $this->assertSame('vacant', DB::table('units')->where('id', $this->unitA)->value('status'));
    }

    public function test_staff_payment_against_a_tenancy_records_the_tenant_and_stores_utc(): void
    {
        Sanctum::actingAs($this->userWithRole('admin'));

        $id = $this->postJson('/api/payments', [
            'tenancy_id' => $this->tenancyA->id,
            'amount' => 20000,
            'method' => 'cash',
            'paid_at' => '2026-09-29T10:15:00+03:00',
        ])->assertCreated()
            ->assertJsonPath('tenant_id', $this->tenancyA->tenant_id)
            ->json('id');

        $this->assertSame('2026-09-29 07:15:00', DB::table('payments')->where('id', $id)->value('paid_at'));
    }

    private function ticket(int $unitId): int
    {
        return DB::table('maintenance_tickets')->insertGetId([
            'unit_id' => $unitId,
            'title' => 'Leaking tap',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
