<?php

namespace Tests\Feature;

use App\Http\Controllers\TenancyController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenancyLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_tenancy_cannot_be_deleted(): void
    {
        $ownerId = DB::table('owners')->insertGetId([
            'fname' => 'Test',
            'lname' => 'Owner',
            'email' => 'owner@example.com',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $propertyId = DB::table('properties')->insertGetId([
            'owner_id' => $ownerId,
            'name' => 'Test Property',
            'address' => 'Nairobi',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $unitId = DB::table('units')->insertGetId([
            'property_id' => $propertyId,
            'name' => 'Unit A1',
            'base_rent' => 20000,
            'status' => 'occupied',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenantId = DB::table('tenants')->insertGetId([
            'fname' => 'Test',
            'lname' => 'Tenant',
            'email' => 'tenant@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenancyId = DB::table('tenancies')->insertGetId([
            'unit_id' => $unitId,
            'tenant_id' => $tenantId,
            'start_date' => now()->toDateString(),
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = app(TenancyController::class)
            ->destroy((string) $tenancyId);

        $this->assertSame(422, $response->getStatusCode());

        $this->assertDatabaseHas('tenancies', [
            'id' => $tenancyId,
            'status' => 'active',
        ]);
    }
}