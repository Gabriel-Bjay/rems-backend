<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
       $this->call([
            RoleSeeder::class,
            AdminUserSeeder::class,
       ]);

       // Demo deployments only: DEMO_PASSWORD turns the demo on. The demo
       // seeder loads its data once and does nothing on later runs. The
       // container seeds on every start, so a failed demo load is logged
       // rather than allowed to stop the API from starting.
       if (config('demo.password')) {
           try {
               $this->call(DemoSeeder::class);
           } catch (\Throwable $e) {
               report($e);
               $this->command?->error('Demo data could not be loaded: '.$e->getMessage());
           }
       }
    }
}
