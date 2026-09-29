<?php

namespace Database\Seeders;

use App\Services\Billing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * A lived-in portfolio for demos: Nairobi properties with a year of rent
 * history run through the real billing flow (invoices, M-Pesa / bank / cash
 * payments applied oldest-first, commissions), a few tenants in arrears,
 * payments awaiting confirmation, maintenance and listings, plus one login
 * per role.
 *
 * php artisan db:seed --class=DemoSeeder   (needs DEMO_PASSWORD in .env)
 */
class DemoSeeder extends Seeder
{
    private const OWNERS = [
        ['Grace', 'Wanjiku', 'grace.wanjiku@example.com'],
        ['Peter', 'Otieno', 'peter.otieno@example.com'],
        ['Amina', 'Hassan', 'amina.hassan@example.com'],
    ];

    private const AGENTS = [
        ['Brian', 'Kamau', 'brian.kamau@example.com', 8],
        ['Faith', 'Achieng', 'faith.achieng@example.com', 10],
    ];

    // name, address, owner index, agent index (null = self-managed), cycle,
    // [unit type => [count, rent]], [charge => amount]
    private const PROPERTIES = [
        ['Kilimani Heights', 'Argwings Kodhek Rd, Kilimani, Nairobi', 0, 0, 'monthly',
            ['1 Bedroom' => [4, 38000], '2 Bedroom' => [4, 55000]], ['Water' => 1500, 'Service charge' => 3500]],
        ['Westlands Court', 'Waiyaki Way, Westlands, Nairobi', 1, 1, 'monthly',
            ['2 Bedroom' => [3, 65000], '3 Bedroom' => [3, 95000]], ['Service charge' => 6000, 'Water' => 2000]],
        ['Ruaka Residences', 'Limuru Rd, Ruaka', 0, 0, 'monthly',
            ['Studio' => [5, 15000], '1 Bedroom' => [5, 22000]], ['Water' => 800, 'Garbage collection' => 300]],
        ['South B Apartments', 'Mukoma Rd, South B, Nairobi', 2, 1, 'monthly',
            ['1 Bedroom' => [4, 25000], '2 Bedroom' => [4, 35000]], ['Water' => 1000, 'Garbage collection' => 500]],
        ['Lavington Villas', 'James Gichuru Rd, Lavington, Nairobi', 1, null, 'quarterly',
            ['4 Bedroom townhouse' => [4, 150000]], ['Service charge' => 10000]],
    ];

    private const TENANTS = [
        'Kevin Mwangi', 'Mercy Njeri', 'Dennis Kiprono', 'Janet Atieno', 'Collins Omondi', 'Lucy Wambui',
        'Samuel Kariuki', 'Esther Chebet', 'Victor Mutua', 'Naomi Wairimu', 'James Njoroge', 'Ruth Akinyi',
        'Moses Kiplagat', 'Diana Nyambura', 'Eric Ochieng', 'Joy Mumbi', 'Alex Kimani', 'Purity Jepchirchir',
        'Tom Odhiambo', 'Carol Muthoni', 'Ian Macharia', 'Winnie Adhiambo', 'George Kilonzo', 'Sharon Wanjiru',
        'Paul Kibet', 'Ann Nduta', 'Mark Onyango', 'Beatrice Wafula', 'Chris Waweru', 'Lilian Moraa',
        'Brenda Chelimo', 'Daniel Mutiso',
    ];

    // Units left vacant (property index => unit numbers), so listings and
    // occupancy have something to show.
    private const VACANT = [0 => [8], 1 => [6], 2 => [9, 10], 3 => [8]];

    private const TICKETS = [
        ['Leaking kitchen tap', 'Water drips constantly from the kitchen mixer.', 'open', null],
        ['No hot water', 'The instant shower heater stopped working last night.', 'in_progress', null],
        ['Broken window latch', 'Bedroom window latch is broken and will not lock.', 'resolved', 2500],
        ['Faulty socket in living room', 'Socket sparks when anything is plugged in.', 'in_progress', null],
        ['Blocked bathroom drain', 'Shower water is not draining.', 'resolved', 1800],
        ['Security light not working', 'The light above the entrance is out.', 'open', null],
        ['Gate remote not responding', 'Gate remote stopped opening the main gate.', 'resolved', 3500],
    ];

    private Billing $billing;
    private CarbonImmutable $today;
    private int $adminId;

    public function run(): void
    {
        $password = env('DEMO_PASSWORD');
        if (! $password) {
            throw new \RuntimeException('DEMO_PASSWORD must be configured to seed the demo logins.');
        }
        if (DB::table('owners')->where('email', self::OWNERS[0][2])->exists()) {
            $this->command?->warn('Demo data is already present; nothing to do.');
            return;
        }

        mt_srand(2026);
        $this->billing = app(Billing::class);
        $this->today = CarbonImmutable::today();
        $this->adminId = DB::table('users')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.slug', 'admin')
            ->value('users.id') ?? $this->user('Demo Admin', 'demo.admin@rems.test', $password, 'admin');

        DB::transaction(function () use ($password) {
            $owners = array_map(fn ($o) => $this->person('owners', $o[0], $o[1], $o[2], ['status' => 'active']), self::OWNERS);
            $agents = array_map(fn ($a) => $this->person('agents', $a[0], $a[1], $a[2], ['commission_rate' => $a[3]]), self::AGENTS);
            $tenants = array_map(function ($name) {
                [$first, $last] = explode(' ', $name);
                return $this->person('tenants', $first, $last, strtolower("$first.$last@example.com"));
            }, self::TENANTS);

            $units = $this->properties($owners, $agents);
            $tenancies = $this->tenancies($units, $tenants);

            foreach ($tenancies as $index => $tenancy) {
                $this->history($tenancy, $this->profileFor($index));
            }

            $this->pendingPayments($tenancies);
            $this->tickets($tenancies, $agents);
            $this->listings();

            // One login per role, linked to a real profile in the data above.
            $this->link('owners', $owners[0], $this->user('Grace Wanjiku', 'demo.owner@rems.test', $password, 'owner'));
            $this->link('agents', $agents[0], $this->user('Brian Kamau', 'demo.agent@rems.test', $password, 'agent'));
            $this->link('tenants', $tenants[0], $this->user('Kevin Mwangi', 'demo.tenant@rems.test', $password, 'tenant'));
            $this->recentNotifications($tenants[0]);
        });

        $this->billing->markOverdue($this->today);
        $this->command?->info('Demo portfolio seeded. Logins: demo.owner@, demo.agent@, demo.tenant@rems.test (DEMO_PASSWORD).');
    }

    /** @return array<int, array<int, object>> units per property index */
    private function properties(array $owners, array $agents): array
    {
        $units = [];
        foreach (self::PROPERTIES as $p => [$name, $address, $owner, $agent, $cycle, $types, $charges]) {
            $propertyId = DB::table('properties')->insertGetId([
                'owner_id' => $owners[$owner],
                'agent_id' => $agent === null ? null : $agents[$agent],
                'name' => $name,
                'address' => $address,
                'description' => "$name, managed through PRMS.",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $number = 0;
            $block = chr(ord('A') + $p);
            foreach ($types as $type => [$count, $rent]) {
                for ($i = 0; $i < $count; $i++) {
                    $number++;
                    $unitId = DB::table('units')->insertGetId([
                        'property_id' => $propertyId,
                        'name' => "$block$number",
                        'type' => $type,
                        'base_rent' => $rent,
                        'status' => 'vacant',
                        'description' => "$type in $name.",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    foreach ($charges as $charge => $amount) {
                        DB::table('unit_charges')->insert([
                            'unit_id' => $unitId,
                            'name' => $charge,
                            'amount' => $amount,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    $units[$p][$number] = (object) ['id' => $unitId, 'cycle' => $cycle];
                }
            }
        }

        return $units;
    }

    private function tenancies(array $units, array $tenants): array
    {
        $tenancies = [];
        $tenantIndex = 0;

        foreach ($units as $p => $propertyUnits) {
            foreach ($propertyUnits as $number => $unit) {
                if (in_array($number, self::VACANT[$p] ?? [], true)) {
                    continue;
                }
                $monthsAgo = mt_rand(3, 15);
                $start = $this->today->subMonthsNoOverflow($monthsAgo)->startOfMonth()->addDays(mt_rand(0, 20));
                // Fixed one-year leases, renewed once when the first year is up;
                // every fourth tenancy is periodic (no end date).
                $end = $tenantIndex % 4 === 3 ? null : $start->addYear()->subDay();
                if ($end && $end->lt($this->today)) {
                    $end = $end->addYear();
                }

                $id = DB::table('tenancies')->insertGetId([
                    'unit_id' => $unit->id,
                    'tenant_id' => $tenants[$tenantIndex],
                    'start_date' => $start->toDateString(),
                    'end_date' => $end?->toDateString(),
                    'billing_cycle' => $unit->cycle,
                    'status' => 'active',
                    'created_at' => $start,
                    'updated_at' => $start,
                ]);
                DB::table('units')->where('id', $unit->id)->update(['status' => 'occupied']);

                $tenancy = DB::table('tenancies')->find($id);
                $this->billing->openDeposit($tenancy);
                DB::table('deposits')->where('tenancy_id', $id)->update([
                    'amount_held' => DB::raw('amount_required'),
                    'status' => 'held',
                    'confirmed_by_user_id' => $this->adminId,
                ]);

                $tenancies[] = $tenancy;
                $tenantIndex++;
            }
        }

        // One vacant unit is out of service rather than simply empty.
        DB::table('units')->where('id', $units[2][10]->id)->update(['status' => 'under_maintenance']);

        return $tenancies;
    }

    /** Most tenants pay on time; some are late, some part-pay, a few stop paying. */
    private function profileFor(int $index): string
    {
        return match (true) {
            $index === 0 => 'current_due',   // the demo tenant owes just this period's rent
            in_array($index, [7, 19], true) => 'defaulter',
            $index % 8 === 5 => 'partial',
            $index % 5 === 2 => 'late',
            default => 'reliable',
        };
    }

    /** Invoice every period from move-in to today and pay them the way this tenant does. */
    private function history(object $tenancy, string $profile): void
    {
        $period = CarbonImmutable::parse($tenancy->start_date);
        $periods = 0;

        while ($period->lte($this->today)) {
            $invoiceId = $this->billing->invoiceFor($tenancy, $period, $this->today);
            [, $periodEnd] = $this->billing->periodFor($tenancy, $period);
            $period = $periodEnd->addDay();
            $periods++;
            if (! $invoiceId) {
                continue;
            }

            $invoice = DB::table('invoices')->find($invoiceId);
            $due = CarbonImmutable::parse($invoice->due_date);
            $isCurrent = $period->gt($this->today);

            [$amount, $paidAt] = match ($profile) {
                'reliable' => [$invoice->total_amount, $due->subDays(mt_rand(0, 4))],
                'current_due' => [$isCurrent ? 0 : $invoice->total_amount, $due->subDays(mt_rand(0, 3))],
                'late' => [$invoice->total_amount, $due->addDays(mt_rand(4, 18))],
                'partial' => [round($invoice->total_amount * (mt_rand(55, 85) / 100), -2), $due->addDays(mt_rand(0, 6))],
                'defaulter' => $periods <= 3 ? [$invoice->total_amount, $due->addDays(mt_rand(0, 9))] : [0, $due],
            };

            if ($amount <= 0 || $paidAt->gt($this->today)) {
                continue;
            }

            $this->pay($tenancy, (float) $amount, $paidAt, completed: true);
        }
    }

    /** Tenant-reported payments that staff have not confirmed yet (never the demo tenant's). */
    private function pendingPayments(array $tenancies): void
    {
        $reported = 0;
        foreach (array_slice($tenancies, 1) as $tenancy) {
            $open = DB::table('invoices')
                ->where('tenancy_id', $tenancy->id)
                ->whereIn('status', Billing::OPEN_STATUSES)
                ->orderBy('due_date')
                ->first();
            if (! $open) {
                continue;
            }
            $this->pay($tenancy, (float) $open->total_amount, $this->today->subDays($reported), completed: false);
            if (++$reported === 3) {
                return;
            }
        }
    }

    private function pay(object $tenancy, float $amount, CarbonImmutable $paidAt, bool $completed): void
    {
        $method = ['mpesa', 'mpesa', 'mpesa', 'mpesa', 'bank', 'bank', 'cash', 'card'][mt_rand(0, 7)];
        $reference = match ($method) {
            'mpesa' => $this->mpesaReference(),
            'bank' => 'BNK' . $paidAt->format('ymd') . mt_rand(1000, 9999),
            'card' => 'CRD' . strtoupper(bin2hex(random_bytes(4))),
            'cash' => null,
        };
        $at = $paidAt->setTime(mt_rand(7, 20), mt_rand(0, 59));

        $id = DB::table('payments')->insertGetId([
            'tenant_id' => $tenancy->tenant_id,
            'tenancy_id' => $tenancy->id,
            'amount' => $amount,
            'method' => $method,
            'paid_at' => $at,
            'gateway_reference' => $reference,
            'status' => 'unmatched',
            'recorded_by_user_id' => $this->adminId,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        if ($completed) {
            DB::table('payments')->where('id', $id)->update([
                'status' => 'completed',
                'confirmed_by_user_id' => $this->adminId,
            ]);
            $this->billing->applyPayment($id, $this->today);
        }
    }

    private function tickets(array $tenancies, array $agents): void
    {
        foreach (self::TICKETS as $i => [$title, $description, $status, $cost]) {
            $tenancy = $tenancies[$i === 0 ? 0 : $i * 4 % count($tenancies)];
            $raised = $this->today->subDays(mt_rand(1, 40))->setTime(mt_rand(8, 19), 0);

            DB::table('maintenance_tickets')->insert([
                'unit_id' => $tenancy->unit_id,
                'tenancy_id' => $tenancy->id,
                'raised_by_tenant_id' => $tenancy->tenant_id,
                'assigned_to_agent_id' => $status === 'open' ? null : $agents[$i % 2],
                'title' => $title,
                'description' => $description,
                'status' => $status,
                'repair_cost' => $cost,
                'resolved_at' => $status === 'resolved' ? $raised->addDays(mt_rand(1, 4)) : null,
                'created_at' => $raised,
                'updated_at' => $raised,
            ]);
        }
    }

    private function listings(): void
    {
        $vacant = DB::table('units')->where('status', 'vacant')->orderBy('id')->get();

        foreach ($vacant as $i => $unit) {
            DB::table('listings')->insert([
                'unit_id' => $unit->id,
                'requested_by_user_id' => $this->adminId,
                'approved_by_user_id' => $i === 0 ? null : $this->adminId,
                'listed_price' => $unit->base_rent,
                'description' => "{$unit->type} available now. Water and security included in service charge.",
                'status' => $i === 0 ? 'requested' : 'live',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function person(string $table, string $first, string $last, string $email, array $extra = []): int
    {
        return DB::table($table)->insertGetId([
            'fname' => $first,
            'lname' => $last,
            'email' => $email,
            'phone' => '+2547' . mt_rand(10000000, 99999999),
            'created_at' => now(),
            'updated_at' => now(),
        ] + $extra);
    }

    private function user(string $name, string $email, string $password, string $role): int
    {
        $userId = DB::table('users')->insertGetId([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('role_user')->insert([
            'user_id' => $userId,
            'role_id' => DB::table('roles')->where('slug', $role)->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $userId;
    }

    private function link(string $table, int $profileId, int $userId): void
    {
        DB::table($table)->where('id', $profileId)->update(['user_id' => $userId]);
    }

    /** The demo tenant's login is linked after the history, so give them a recent feed from it. */
    private function recentNotifications(int $tenantId): void
    {
        $userId = DB::table('tenants')->where('id', $tenantId)->value('user_id');
        $tenancyIds = DB::table('tenancies')->where('tenant_id', $tenantId)->pluck('id');
        $invoice = DB::table('invoices')->whereIn('tenancy_id', $tenancyIds)->orderByDesc('issue_date')->first();
        $payment = DB::table('payments')->where('tenant_id', $tenantId)
            ->where('status', 'completed')->orderByDesc('paid_at')->first();
        $ticket = DB::table('maintenance_tickets')->where('raised_by_tenant_id', $tenantId)->orderByDesc('id')->first();

        $feed = array_filter([
            $payment ? ['payment_received', sprintf('Payment of %s received. Thank you.',
                $this->billing->money($payment->amount)), '/app/payments', $payment->paid_at, true] : null,
            $invoice ? ['invoice_issued', sprintf('Invoice for %s: %s due %s.',
                CarbonImmutable::parse($invoice->period_start)->format('M Y'),
                $this->billing->money($invoice->total_amount),
                CarbonImmutable::parse($invoice->due_date)->format('j M')),
                "/app/invoices/{$invoice->id}", $invoice->issue_date, false] : null,
            $ticket ? ['maintenance_update', "\"{$ticket->title}\" was received. An agent will be assigned shortly.",
                '/app/maintenance', $ticket->created_at, false] : null,
        ]);

        foreach ($feed as [$event, $message, $url, $at, $read]) {
            DB::table('notifications')->insert([
                'user_id' => $userId,
                'event_type' => $event,
                'message' => $message,
                'related_url' => $url,
                'is_read' => $read,
                'read_at' => $read ? $at : null,
                'delivery_channel' => 'in_app',
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
    }

    /** M-Pesa confirmation codes look like "QK7X2ZP4TA": ten uppercase letters and digits. */
    private function mpesaReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ0123456789';
        $code = 'Q';
        for ($i = 0; $i < 9; $i++) {
            $code .= $alphabet[mt_rand(0, strlen($alphabet) - 1)];
        }

        return $code;
    }
}
