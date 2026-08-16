<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            throw new \RuntimeException(
                'ADMIN_EMAIL and ADMIN_PASSWORD must be configured.'
            );
        }

        DB::transaction(function () use ($email, $password) {
            $now = now();

            DB::table('users')->updateOrInsert(
                ['email' => $email],
                [
                    'name' => 'REMS Administrator',
                    'password' => Hash::make($password),
                    'status' => 'active',
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $admin = DB::table('users')
                ->where('email', $email)
                ->first();

            $adminRole = DB::table('roles')
                ->where('slug', 'admin')
                ->first();

            if (! $adminRole) {
                throw new \RuntimeException(
                    'The admin role does not exist. Run RoleSeeder before AdminUserSeeder.'
                );
            }

            DB::table('role_user')->updateOrInsert(
                [
                    'user_id' => $admin->id,
                    'role_id' => $adminRole->id,
                ],
                [
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        });
    }
}
