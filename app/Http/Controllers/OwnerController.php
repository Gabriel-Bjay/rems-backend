<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;


class OwnerController extends Controller
{
    //Return list of all owners
    public function index()
    {
        $owners = DB::table('owners')->orderBy('id')->get();
        return 
            response()->json($owners)
        ;
    }
    //save a new owner function 
    public function store(Request $request)
    {
        $data = $request->validate([
            'fname' => ['required', 'string', 'max:100'],
            'lname' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:owners,email'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('owners')->insertGetId($data);
        $owner = DB::table('owners')->find($id);

        return
            response()->json($owner, 201)
        ;
    }
    //Get owner by specific id
   public function show(string $id)
    {
        $owner = DB::table('owners')->find($id);

        if (! $owner) {
            return response()->json(['message' => 'Owner not found.'], 404);
        }

        return response()->json($owner);
    }
    //Edit owner using specific id
    public function update(Request $request, string $id)
    {
        $owner = DB::table('owners')->find($id);

        if (! $owner) {
            return
                response()->json(['message' => 'Owner not found.'], 404)
            ;
        }

        $data = $request->validate([
            'fname' => ['required', 'string', 'max:100'],
            'lname'  => ['required', 'string', 'max:100'],
            'email'      => ['required', 'email', 'max:255', Rule::unique('owners', 'email')->ignore($id)],
            'phone'      => ['nullable', 'string', 'max:30'],
        ]);

        $data['updated_at'] = now();

        DB::table('owners')->where('id', $id)->update($data);
        $owner = DB::table('owners')->find($id);

        return 
            response()->json($owner)
        ;
    }
    //Delete owner record using specific owner id
    public function destroy(string $id)
    {
        $owner = DB::table('owners')->find($id);

        if (! $owner) {
            return response()->json(['message' => 'Owner not found.'], 404);
        }

        DB::table('owners')->where('id', $id)->delete();

        return
            response()->json(null, 204)
        ;
    }
}
