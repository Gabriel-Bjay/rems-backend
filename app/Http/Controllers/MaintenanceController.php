<?php

namespace App\Http\Controllers;

use App\Services\Access;
use App\Services\Billing;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenanceController extends Controller
{
    public function index(Request $request)
    {
        return response()->json($this->visible($request)->get());
    }

    /**
     * Staff raise tickets for any unit, owners for units they own, and
     * tenants for the unit they currently rent.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'tenancy_id' => ['nullable', 'integer', 'exists:tenancies,id'],
            'raised_by_tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
        ]);

        $access = Access::for($request->user());
        if (! $access->is('admin', 'agent')) {
            $ownsUnit = $access->ownerId() && in_array((int) $data['unit_id'], $access->managedUnitIds(), true);
            $tenancy = $access->tenantId()
                ? DB::table('tenancies')
                    ->where('tenant_id', $access->tenantId())
                    ->where('unit_id', $data['unit_id'])
                    ->where('status', 'active')
                    ->first()
                : null;

            if ($tenancy) {
                $data['raised_by_tenant_id'] = $access->tenantId();
                $data['tenancy_id'] = $tenancy->id;
            } elseif (! $ownsUnit) {
                return response()->json([
                    'message' => 'You can only raise requests for a unit you rent or own.',
                ], 422);
            }
        }

        $data['tenancy_id'] = $data['tenancy_id'] ?? null;
        $data['raised_by_tenant_id'] = $data['raised_by_tenant_id'] ?? null;
        $data['description'] = $data['description'] ?? null;

        $data['assigned_to_agent_id'] = null;
        $data['status'] = 'open';
        $data['repair_cost'] = null;
        $data['resolved_at'] = null;

        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('maintenance_tickets')->insertGetId($data);

        return response()->json($this->visible($request)->where('maintenance_tickets.id', $id)->first(), 201);
    }

    public function show(Request $request, string $id)
    {
        $ticket = $this->visible($request)->where('maintenance_tickets.id', $id)->first();

        if (!$ticket) {
            return response()->json(['message' => 'Maintenance ticket not found'], 404);
        }

        return
            response()->json($ticket);
    }

    public function update(Request $request, string $id)
    {
        $ticket = DB::table('maintenance_tickets')->find($id);

        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $data = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'tenancy_id' => ['nullable', 'integer', 'exists:tenancies,id'],
            'raised_by_tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
        ]);

        $data['tenancy_id'] = $data['tenancy_id'] ?? null;
        $data['raised_by_tenant_id'] = $data['raised_by_tenant_id'] ?? null;
        $data['description'] = $data['description'] ?? null;
        $data['updated_at'] = now();

        DB::table('maintenance_tickets')->where('id', $id)->update($data);

        return response()->json($this->visible($request)->where('maintenance_tickets.id', $id)->first());
    }

    public function destroy(string $id)
    {
        $ticket = DB::table('maintenance_tickets')->find($id);

        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        DB::table('maintenance_tickets')->where('id', $id)->delete();

        return response()->json(null, 204);
    }

    public function assign(Request $request, string $id, Billing $billing)
    {
        $ticket = $this->visible($request)->where('maintenance_tickets.id', $id)->first();

        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $data = $request->validate([
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
        ]);

        // Agents pick tickets up themselves; only an admin hands one to someone else.
        $access = Access::for($request->user());
        if (! $access->isAdmin()) {
            $data['agent_id'] = $access->agentId();
        }

        if (empty($data['agent_id'])) {
            return response()->json(['message' => 'Choose the agent who will handle this ticket.'], 422);
        }

        if (in_array($ticket->status, ['resolved', 'closed'])) {
            return response()->json([
                'message' => 'A resolved or closed ticket cannot be assigned.',
            ], 422);
        }

        DB::table('maintenance_tickets')->where('id', $id)->update([
            'assigned_to_agent_id' => $data['agent_id'],
            'status' => 'in_progress',
            'updated_at' => now(),
        ]);

        $agent = DB::table('agents')->find($data['agent_id']);
        $billing->notifyTenant(
            $ticket->raised_by_tenant_id,
            'maintenance_update',
            "\"{$ticket->title}\" is being handled by {$agent->fname} {$agent->lname}.",
            '/app/maintenance',
        );

        return response()->json($this->visible($request)->where('maintenance_tickets.id', $id)->first());
    }

    public function resolve(Request $request, string $id, Billing $billing)
    {
        $ticket = $this->visible($request)->where('maintenance_tickets.id', $id)->first();

        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $data = $request->validate([
            'repair_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (! in_array($ticket->status, ['open', 'in_progress'])) {
            return response()->json([
                'message' => 'Only an open or in-progress ticket can be resolved.',
            ], 422);
        }

        DB::table('maintenance_tickets')->where('id', $id)->update([
            'status' => 'resolved',
            'repair_cost' => $data['repair_cost'] ?? null,
            'resolved_at' => now(),
            'updated_at' => now(),
        ]);

        $billing->notifyTenant(
            $ticket->raised_by_tenant_id,
            'maintenance_update',
            "\"{$ticket->title}\" has been resolved.",
            '/app/maintenance',
        );

        return response()->json($this->visible($request)->where('maintenance_tickets.id', $id)->first());
    }

    /** Tickets the user may see, with unit, property, agent and reporter names. */
    private function visible(Request $request): Builder
    {
        $access = Access::for($request->user());

        $query = DB::table('maintenance_tickets')
            ->join('units', 'units.id', '=', 'maintenance_tickets.unit_id')
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->leftJoin('agents', 'agents.id', '=', 'maintenance_tickets.assigned_to_agent_id')
            ->leftJoin('tenants', 'tenants.id', '=', 'maintenance_tickets.raised_by_tenant_id')
            ->select(
                'maintenance_tickets.*',
                'units.name as unit_name',
                'properties.name as property_name',
                'agents.fname as agent_fname',
                'agents.lname as agent_lname',
                'tenants.fname as tenant_fname',
                'tenants.lname as tenant_lname',
            )
            ->orderByDesc('maintenance_tickets.id');

        if (! $access->isAdmin()) {
            $query->where(function ($q) use ($access) {
                $q->whereIn('maintenance_tickets.unit_id', $access->managedUnitIds());
                if ($access->tenantId()) {
                    $q->orWhere('maintenance_tickets.raised_by_tenant_id', $access->tenantId())
                        ->orWhereIn('maintenance_tickets.tenancy_id', $access->tenancyIds());
                }
            });
        }

        return $query;
    }
}
