<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    //Create a new user account.
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'   => ['required', 'string', 'min:8'],
            'role'       => ['required', Rule::in(['admin', 'owner', 'agent', 'tenant'])],
            'profile_id' => ['nullable', 'integer'],
        ]);

        $profileTable = match ($data['role']) {
            'owner' => 'owners',
            'agent' => 'agents',
            'tenant' => 'tenants',
            default => null,
        };

        if ($profileTable && !empty($data['profile_id'])) {

            $profile = DB::table($profileTable)->find($data['profile_id']);

            if (!$profile) {
                return response()->json([
                    'message' => 'The selected profile does not exist.'
                ], 422);
            }

            if ($profile->user_id !== null) {
                return response()->json([
                    'message' => 'That profile is already linked to a user account.'
                ], 422);
            }
        }

        $role = DB::table('roles')
            ->where('slug', $data['role'])
            ->first();

        if (!$role) {
            return response()->json([
                'message' => 'The selected role does not exist.'
            ], 422);
        }

        $userId = DB::transaction(function () use ($data, $profileTable, $role) {

            $userId = DB::table('users')->insertGetId([
                'name'       => $data['name'],
                'email'      => $data['email'],
                'password'   => Hash::make($data['password']),
                'status'     => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('role_user')->insert([
                'user_id'    => $userId,
                'role_id'    => $role->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($profileTable && !empty($data['profile_id'])) {
                DB::table($profileTable)
                    ->where('id', $data['profile_id'])
                    ->update([
                        'user_id'    => $userId,
                        'updated_at' => now(),
                    ]);
            }

            return $userId;
        });

        $user = User::find($userId);

        $roles = DB::table('roles')
            ->join('role_user', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $userId)
            ->pluck('roles.slug');

        return response()->json([
            'message' => 'User created successfully.',
            'user' => [
                'id'     => $user->id,
                'name'   => $user->name,
                'email'  => $user->email,
                'status' => $user->status,
                'roles'  => $roles,
            ]
        ], 201);
    }

    //Login user.
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.'
            ], 401);
        }

        $roles = DB::table('roles')
            ->join('role_user', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->pluck('roles.slug');

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id'     => $user->id,
                'name'   => $user->name,
                'email'  => $user->email,
                'status' => $user->status,
                'roles'  => $roles,
            ]
        ]);
    }

    //Logout current user
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.'
        ]);
    }

    // Return the authenticated user
    public function me(Request $request)
    {
        $user = $request->user();

        $roles = DB::table('roles')
            ->join('role_user', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->pluck('roles.slug');

        return response()->json([
            'id'     => $user->id,
            'name'   => $user->name,
            'email'  => $user->email,
            'status' => $user->status,
            'roles'  => $roles,
        ]);
    }
}