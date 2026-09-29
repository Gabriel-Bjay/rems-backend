<?php

namespace Tests\Feature;

use App\Services\Billing;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\BuildsPortfolio;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use BuildsPortfolio;
    use RefreshDatabase;

    public function test_dashboard_summarises_the_portfolio_for_admins_and_the_home_for_tenants(): void
    {
        $this->setUpRoles();
        $billing = app(Billing::class);
        $owner = $this->person('owners', ['status' => 'active']);
        $tenant = $this->person('tenants');
        $property = $this->property($owner);
        $this->unit($property, 15000);
        $tenancy = $this->tenancy($this->unit($property, 20000, [], 'occupied'), $tenant,
            now()->subMonth()->startOfMonth()->toDateString());

        $billing->invoiceFor($tenancy, CarbonImmutable::today()->subMonth()->startOfMonth());
        $billing->invoiceFor($tenancy, CarbonImmutable::today());
        $billing->applyPayment($this->payment($tenancy, 20000, 'completed'));
        $this->payment($tenancy, 20000);

        Sanctum::actingAs($this->userWithRole('admin'));
        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('view', 'admin')
            ->assertJsonPath('portfolio.units', 2)
            ->assertJsonPath('portfolio.occupied', 1)
            ->assertJsonPath('portfolio.occupancy_rate', 0.5)
            ->assertJsonPath('finance.outstanding', 20000)
            ->assertJsonPath('finance.pending_confirmations', 1)
            ->assertJsonCount(6, 'trend')
            ->assertJsonPath('tenant', null);

        Sanctum::actingAs($this->userWithRole('tenant', $tenant));
        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('view', 'tenant')
            ->assertJsonPath('portfolio.units', 1)
            ->assertJsonPath('tenant.balance', 20000)
            ->assertJsonPath('tenant.tenancy.id', $tenancy->id);
    }
}
