<?php

namespace Tests\Feature\Concerns;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;

trait BuildsPortfolio
{
    private int $sequence = 0;

    protected function setUpRoles(): void
    {
        $this->seed(RoleSeeder::class);
    }

    /** A user with $role; for owner/agent/tenant the matching profile is linked. */
    protected function userWithRole(string $role, ?int $profileId = null): User
    {
        $n = ++$this->sequence;
        $user = User::query()->forceCreate([
            'name' => ucfirst($role)." $n",
            'email' => "$role$n@test.local",
            'password' => bcrypt('secret-pass'),
            'status' => 'active',
        ]);
        DB::table('role_user')->insert([
            'user_id' => $user->id,
            'role_id' => DB::table('roles')->where('slug', $role)->value('id'),
        ]);

        $table = ['owner' => 'owners', 'agent' => 'agents', 'tenant' => 'tenants'][$role] ?? null;
        if ($table && $profileId) {
            DB::table($table)->where('id', $profileId)->update(['user_id' => $user->id]);
        }

        return $user;
    }

    protected function person(string $table, array $extra = []): int
    {
        $n = ++$this->sequence;

        return DB::table($table)->insertGetId([
            'fname' => ucfirst(rtrim($table, 's')),
            'lname' => "No$n",
            'email' => rtrim($table, 's')."$n@test.local",
            'created_at' => now(),
            'updated_at' => now(),
        ] + $extra);
    }

    protected function property(int $ownerId, ?int $agentId = null): int
    {
        return DB::table('properties')->insertGetId([
            'owner_id' => $ownerId,
            'agent_id' => $agentId,
            'name' => 'Property '.(++$this->sequence),
            'address' => 'Nairobi',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<string, float> $charges */
    protected function unit(int $propertyId, float $rent, array $charges = [], string $status = 'vacant'): int
    {
        $id = DB::table('units')->insertGetId([
            'property_id' => $propertyId,
            'name' => 'U'.(++$this->sequence),
            'type' => '1 Bedroom',
            'base_rent' => $rent,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach ($charges as $name => $amount) {
            DB::table('unit_charges')->insert([
                'unit_id' => $id,
                'name' => $name,
                'amount' => $amount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $id;
    }

    protected function tenancy(int $unitId, int $tenantId, string $start, string $status = 'active', string $cycle = 'monthly'): object
    {
        $id = DB::table('tenancies')->insertGetId([
            'unit_id' => $unitId,
            'tenant_id' => $tenantId,
            'start_date' => $start,
            'billing_cycle' => $cycle,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('tenancies')->find($id);
    }

    protected function payment(object $tenancy, float $amount, string $status = 'unmatched'): int
    {
        return DB::table('payments')->insertGetId([
            'tenant_id' => $tenancy->tenant_id,
            'tenancy_id' => $tenancy->id,
            'amount' => $amount,
            'method' => 'mpesa',
            'paid_at' => now(),
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
