<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AgentController extends Controller
{
    //Show list of all agents
    public function index()
    {
        $agents = DB::table('agents')->orderBy('id')->get();

        return response()->json($agents);
    }
    //Save new agent
    public function store(Request $request)
    {
        $data = $request->validate([
            'fname' => ['required', 'string', 'max:100'],
            'lname'  => ['required', 'string', 'max:100'],
            'email'      => ['required', 'email', 'max:255', 'unique:agents,email'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('agents')->insertGetId($data);
        $agent = DB::table('agents')->find($id);

        return response()->json($agent, 201);
    }
    //Return single agent record using specific id
    public function show(string $id)
    {
        $agent = DB::table('agents')->find($id);

        if (! $agent) {
            return response()->json(['message' => 'Agent not found.'], 404);
        }

        return response()->json($agent);
    }
    //Update agent record using specific id
    public function update(Request $request, string $id)
    {
        $agent = DB::table('agents')->find($id);

        if (! $agent) {
            return response()->json(['message' => 'Agent not found.'], 404);
        }

        $data = $request->validate([
            'fname' => ['required', 'string', 'max:100'],
            'lname'  => ['required', 'string', 'max:100'],
            'email'      => ['required', 'email', 'max:255', Rule::unique('agents', 'email')->ignore($id)],
            'phone'      => ['nullable', 'string', 'max:30'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $data['phone'] = $data['phone'] ?? null;
        $data['commission_rate'] = $data['commission_rate'] ?? 0;
        $data['updated_at'] = now();
        

        DB::table('agents')->where('id', $id)->update($data);
        $agent = DB::table('agents')->find($id);

        return response()->json($agent);
    }
    //Delete agent record using specific agent id
    public function destroy(string $id)
    {
        $agent = DB::table('agents')->find($id);

        if (! $agent) {
            return response()->json(['message' => 'Agent not found.'], 404);
        }

        DB::table('agents')->where('id', $id)->delete();

        return response()->json(null, 204);
    }
}