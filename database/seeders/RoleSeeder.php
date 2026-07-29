<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Admin', 'slug' => 'admin'],
            ['name' => 'Agent', 'slug' => 'agent'],
            ['name' => 'Owner', 'slug' => 'owner'],
            ['name' => 'Tenant', 'slug' => 'tenant'],
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}
