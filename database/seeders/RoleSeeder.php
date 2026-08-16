<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('roles')->upsert(
            [
                [
                    'name' => 'Admin',
                    'slug' => 'admin',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Owner',
                    'slug' => 'owner',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Agent',
                    'slug' => 'agent',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Tenant',
                    'slug' => 'tenant',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['slug'],
            ['name', 'updated_at']
        );
    }
}