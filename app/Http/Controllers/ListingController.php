<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ListingController extends Controller
{
    public function index()
    {
        $listings = DB::table('listings')->orderByDesc('id')->get();

        return 
            response()->json($listings, 200);
    }
     public function store(Request $request)
    {
        $data = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'listed_price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $data['description'] = $data['description'] ?? null;

        $data['requested_by_user_id'] = $request->user()->id;

        $data['approved_by_user_id'] = null;
        $data['status'] = 'requested';

        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('listings')->insertGetId($data);

        $listing = DB::table('listings')->find($id);

        return response()->json($listing, 201);
    }

    public function show(string $id)
    {
        $listing = DB::table('listings')->find($id);

        if (! $listing) {
            return response()->json(['message' => 'Listing not found.'], 404);
        }

        return response()->json($listing);
    }

    public function update(Request $request, string $id)
    {
        $listing = DB::table('listings')->find($id);

        if (! $listing) {
            return response()->json(['message' => 'Listing not found.'], 404);
        }

        $data = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'listed_price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $data['description'] = $data['description'] ?? null;
        $data['updated_at'] = now();

        DB::table('listings')->where('id', $id)->update($data);

        $listing = DB::table('listings')->find($id);

        return response()->json($listing);
    }

    public function destroy(string $id)
    {
        $listing = DB::table('listings')->find($id);

        if (! $listing) {
            return response()->json(['message' => 'Listing not found.'], 404);
        }

        DB::table('listings')->where('id', $id)->delete();

        return response()->json(null, 204);
    }

    public function approve(Request $request, string $id)
    {
        $listing = DB::table('listings')->find($id);

        if (! $listing) {
            return response()->json(['message' => 'Listing not found.'], 404);
        }

        if ($listing->status !== 'requested') {
            return response()->json([
                'message' => 'Only a requested listing can be approved.',
            ], 422);
        }

        DB::table('listings')->where('id', $id)->update([
            'approved_by_user_id' => $request->user()->id,
            'status' => 'approved',
            'updated_at' => now(),
        ]);

        $listing = DB::table('listings')->find($id);

        return response()->json($listing);
    }

    public function publish(string $id)
    {
        $listing = DB::table('listings')->find($id);

        if (! $listing) {
            return response()->json(['message' => 'Listing not found.'], 404);
        }

        if ($listing->status !== 'approved') {
            return response()->json([
                'message' => 'Only an approved listing can be published.',
            ], 422);
        }

        DB::table('listings')->where('id', $id)->update([
            'status' => 'live',
            'updated_at' => now(),
        ]);

        $listing = DB::table('listings')->find($id);

        return response()->json($listing);
    }

    public function takeDown(string $id)
    {
        $listing = DB::table('listings')->find($id);

        if (! $listing) {
            return response()->json(['message' => 'Listing not found.'], 404);
        }

        if (! in_array($listing->status, ['approved', 'live'])) {
            return response()->json([
                'message' => 'Only an approved or live listing can be taken down.',
            ], 422);
        }

        DB::table('listings')->where('id', $id)->update([
            'status' => 'taken_down',
            'updated_at' => now(),
        ]);

        $listing = DB::table('listings')->find($id);

        return response()->json($listing);
    }
}
