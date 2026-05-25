<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ChangePasswordRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\FeatureAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use AppliesSchoolScope;

    public function __construct(private readonly FeatureAccessService $featureAccess)
    {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()
            ->with('role')
            ->where('username', (string) $validated['username'])
            ->where('status_id', 1)
            ->where('is_deleted', 0)
            ->first();

        if ($user === null || !Hash::check((string) $validated['password'], (string) $user->password)) {
            Log::warning('Login failed.', [
                'username' => (string) $validated['username'],
            ]);

            return response()->json([
                'message' => 'Invalid username or password.',
            ], 401);
        }

        $resolvedCensusId = null;
        if (!$this->isAdministrator($user)) {
            $resolvedCensusId = $this->resolveUserCensusId($user);
            if ($resolvedCensusId === null) {
                return response()->json([
                    'message' => 'User is not assigned to a school. Contact administrator.',
                ], 403);
            }
        }

        $plainToken = Str::random(64);

        ApiToken::query()->create([
            'user_id' => (int) $user->user_id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addHours(12),
        ]);

        Log::info('Login success.', [
            'user_id' => (int) $user->user_id,
            'username' => (string) $user->username,
            'role_id' => isset($user->role_id) ? (int) $user->role_id : null,
            'census_id' => $resolvedCensusId,
        ]);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $plainToken,
            'user' => $this->formatUser($user, $resolvedCensusId),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->attributes->get('auth_user');

        if ($user === null) {
            return response()->json([
                'message' => 'User not found.',
            ], 401);
        }

        $resolvedCensusId = $this->isAdministrator($user)
            ? $this->resolveRequestedSchoolCensusId($user)
            : $this->resolveUserCensusId($user);

        return response()->json([
            'user' => $this->formatUser($user, $resolvedCensusId),
        ]);
    }

    public function account(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->attributes->get('auth_user');

        if ($user === null) {
            return response()->json([
                'message' => 'User not found.',
            ], 401);
        }

        return response()->json([
            'data' => [
                'username' => (string) $user->username,
                'role_id' => isset($user->role_id) ? (int) $user->role_id : null,
                'role_name' => $user->role?->role_name,
                'created_at' => $this->extractCreatedAt($user),
                'is_enabled' => $this->isAccountEnabled($user),
            ],
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->attributes->get('auth_user');

        if ($user === null) {
            return response()->json([
                'message' => 'User not found.',
            ], 401);
        }

        $validated = $request->validated();

        if (!Hash::check((string) $validated['current_password'], (string) $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
            ], 422);
        }

        $user->password = Hash::make((string) $validated['password']);
        if (Schema::hasColumn('user_tbl', 'date_updated')) {
            $user->date_updated = now();
        }
        $user->save();

        Log::info('Password changed.', [
            'user_id' => (int) $user->user_id,
            'username' => (string) $user->username,
            'role_id' => isset($user->role_id) ? (int) $user->role_id : null,
        ]);

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('auth_token');

        if ($token !== null) {
            $token->delete();
        }

        $user = $request->attributes->get('auth_user');
        if ($user instanceof User) {
            Log::info('Logout.', [
                'user_id' => (int) $user->user_id,
                'username' => (string) $user->username,
                'role_id' => isset($user->role_id) ? (int) $user->role_id : null,
            ]);
        } else {
            Log::info('Logout.', [
                'user_id' => null,
            ]);
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatUser(User $user, ?string $resolvedCensusId): array
    {
        return [
            'user_id' => (int) $user->user_id,
            'username' => (string) $user->username,
            'role_id' => isset($user->role_id) ? (int) $user->role_id : null,
            'role_name' => $user->role?->role_name,
            'school_census_id' => $resolvedCensusId,
            'feature_permissions' => $this->featureAccess->permissionMapForUser($user, $resolvedCensusId),
        ];
    }

    private function extractCreatedAt(User $user): ?string
    {
        $value = null;

        if (Schema::hasColumn('user_tbl', 'date_added')) {
            $value = $user->date_added ?? null;
        } elseif (Schema::hasColumn('user_tbl', 'created_at')) {
            $value = $user->created_at ?? null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        $stringValue = trim((string) $value);

        return $stringValue !== '' ? $stringValue : null;
    }

    private function isAccountEnabled(User $user): bool
    {
        $isDeleted = Schema::hasColumn('user_tbl', 'is_deleted')
            ? (int) ($user->is_deleted ?? 0) === 1
            : false;

        $isActiveStatus = Schema::hasColumn('user_tbl', 'status_id')
            ? (int) ($user->status_id ?? 0) === 1
            : true;

        return !$isDeleted && $isActiveStatus;
    }
}
