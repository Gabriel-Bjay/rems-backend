<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenanceController extends Controller
{
    public function index()
    {
        $tickets = DB::table('maintenance_tickets')->orderByDesc('id')->get();
        return 
            response()->json($tickets);
    }

    public function store(Request $request)
    {
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

        $data['assigned_to_agent_id'] = null;
        $data['status'] = 'open';
        $data['repair_cost'] = null;
        $data['resolved_at'] = null;

        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('maintenance_tickets')->insertGetId($data);

        $ticket = DB::table('maintenance_tickets')->find($id);

        return response()->json($ticket, 201);
    }

    public function show(string $id)
    {
        $ticket = DB::table('maintenance_tickets')->find($id);

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

        $ticket = DB::table('maintenance_tickets')->find($id);

        return response()->json($ticket);
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

    public function assign(Request $request, string $id)
    {
        $ticket = DB::table('maintenance_tickets')->find($id);

        if (! $ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $data = $request->validate([
            'agent_id' => ['required', 'integer', 'exists:agents,id'],
        ]);

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

        $ticket = DB::table('maintenance_tickets')->find($id);

        return response()->json($ticket);
    }

    public function resolve(Request $request, string $id)
    {
        $ticket = DB::table('maintenance_tickets')->find($id);

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

        $ticket = DB::table('maintenance_tickets')->find($id);

        return response()->json($ticket);
    }
}

