<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsPortfolio;
use Tests\TestCase;

/** One-click demo sign-in, which only exists while DEMO_PASSWORD turns the demo on. */
class DemoLoginTest extends TestCase
{
    use BuildsPortfolio;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRoles();
        config(['demo.password' => 'demo-secret-for-tests']);
    }

    public function test_the_seeded_demo_accounts_are_offered_and_the_seeder_runs_once(): void
    {
        $this->seed(DemoSeeder::class);
        $invoices = DB::table('invoices')->count();
        $this->seed(DemoSeeder::class);

        $this->assertGreaterThan(0, $invoices);
        $this->assertSame($invoices, DB::table('invoices')->count());
        $this->getJson('/api/demo-accounts')
            ->assertOk()
            ->assertJsonPath('accounts', [
                ['role' => 'owner', 'name' => 'Grace Wanjiku'],
                ['role' => 'agent', 'name' => 'Brian Kamau'],
                ['role' => 'tenant', 'name' => 'Kevin Mwangi'],
            ]);
    }

    public function test_a_visitor_signs_in_as_a_demo_role_with_an_expiring_token(): void
    {
        $this->demoUser('tenant');

        $token = $this->postJson('/api/demo-login', ['role' => 'tenant'])
            ->assertOk()
            ->assertJsonPath('user.roles', ['tenant'])
            ->json('token');

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', config('demo.accounts.tenant'));
        $this->assertNotNull(DB::table('personal_access_tokens')->where('name', 'demo')->value('expires_at'));
    }

    public function test_admin_is_never_offered_for_demo_sign_in(): void
    {
        $this->postJson('/api/demo-login', ['role' => 'admin'])->assertStatus(422);
    }

    public function test_nothing_is_offered_while_the_demo_is_off(): void
    {
        $this->demoUser('tenant');
        config(['demo.password' => null]);

        $this->getJson('/api/demo-accounts')->assertOk()->assertJsonCount(0, 'accounts');
        $this->postJson('/api/demo-login', ['role' => 'tenant'])->assertNotFound();
    }

    private function demoUser(string $role): void
    {
        $user = $this->userWithRole($role);
        $user->forceFill(['email' => config("demo.accounts.$role")])->save();
    }
}
