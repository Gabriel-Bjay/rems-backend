<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    //Create a user account, assign its initial role
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['admin', 'owner', 'agent', 'tenant'])],

            // Required for owner, agent, and tenant accounts.
            // Admin accounts are intentionally not linked to a business profile.
            'profile_id' => [
                'nullable',
                'integer',
                'required_unless:role,admin',
                'prohibited_if:role,admin',
            ],
        ]);

        $profileTable = match ($data['role']) {
            'owner' => 'owners',
            'agent' => 'agents',
            'tenant' => 'tenants',
            'admin' => null,
        };

        $profileId = $data['profile_id'] ?? null;

        $userId = DB::transaction(function () use ($data, $profileTable, $profileId) {
            $role = DB::table('roles')
                ->where('slug', $data['role'])
                ->first();

            if (!$role) {
                throw ValidationException::withMessages([
                    'role' => ['The selected role does not exist.'],
                ]);
            }

            /*
             * Lock the profile row inside the transaction.
             * This prevents two simultaneous requests from linking
             * the same owner, agent, or tenant to different accounts.
             */
            if ($profileTable && $profileId) {
                $profile = DB::table($profileTable)
                    ->where('id', $profileId)
                    ->lockForUpdate()
                    ->first();

                if (!$profile) {
                    throw ValidationException::withMessages([
                        'profile_id' => ['The selected profile does not exist.'],
                    ]);
                }

                if ($profile->user_id !== null) {
                    throw ValidationException::withMessages([
                        'profile_id' => [
                            'That profile is already linked to a user account.',
                        ],
                    ]);
                }
            }

            $now = now();

            $userId = DB::table('users')->insertGetId([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('role_user')->insert([
                'user_id' => $userId,
                'role_id' => $role->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($profileTable && $profileId) {
                DB::table($profileTable)
                    ->where('id', $profileId)
                    ->update([
                        'user_id' => $userId,
                        'updated_at' => $now,
                    ]);
            }

            return $userId;
        });

        $user = DB::table('users')
            ->where('id', $userId)
            ->first();

        return response()->json([
            'message' => 'User created successfully.',
            'user' => $this->userPayload($user),
        ], 201);
    }

    //Authenticate a user and create a Sanctum token.
    
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'This account is not active.',
            ], 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    // Delete the current Sanctum access token.
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    
    //Return the currently authenticated user.
    public function me(Request $request)
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }
     //Build one consistent user response for register, login, and /me.

    private function userPayload(object $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'roles' => $this->getUserRoles($user->id),
        ];
    }

    // Get every RBAC role assigned to a user.

    private function getUserRoles(int $userId): array
    {
        return DB::table('roles')
            ->join('role_user', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $userId)
            ->orderBy('roles.id')
            ->pluck('roles.slug')
            ->values()
            ->all();
    }
}