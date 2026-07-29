<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    //Create new User Login
public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'owner', 'agent', 'tenant'])],
            'profile_id' => ['nullable', 'integer'],
        ]);

        $profileTable = match ($data['role']) {
            'owner' => 'owners',
            'agent' => 'agents',
            'tenant' => 'tenants',
            default => null,
        };

        if ($profileTable && ! empty($data['profile_id'])) {
            $profile = DB::table($profileTable)->find($data['profile_id']);

            if (! $profile) {
                return response()->json(['message' => 'The selected profile does not exist.'], 422);
            }

            if ($profile->user_id !== null) {
                return response()->json(['message' => 'That profile is already linked to a user account.'], 422);
            }
        }

            $userId = DB::transaction(function () use ($data, $profileTable) {
            $userId = DB::table('users')->insertGetId([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($profileTable && ! empty($data['profile_id'])) {
                DB::table($profileTable)->where('id', $data['profile_id'])->update([
                    'user_id' => $userId,
                    'updated_at' => now(),
                ]);
            }

            return $userId;
        });

        $user = DB::table('users')
            ->select('id', 'name', 'email', 'role', 'status')
            ->find($userId);

        return response()->json($user, 201);
    }

    //Login function
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = DB::table('users')
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        $token = User::find($user->id)->createToken('admin-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
            ],
        ]);
    }
    //Logout function
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
    //Get current logged in user function
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
        ]);
    }
}