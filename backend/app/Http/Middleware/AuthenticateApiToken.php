<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Models\ApiToken;
use App\Models\SdsUser;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    use AppliesSchoolScope;

    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $this->extractBearerToken($request);
        if ($plainToken === null) {
            return $this->unauthorizedResponse('Missing or invalid authorization token.');
        }

        $hashedToken = hash('sha256', $plainToken);

        $apiToken = ApiToken::query()
            ->where('token_hash', $hashedToken)
            ->first();

        if ($apiToken === null) {
            return $this->unauthorizedResponse('Invalid authorization token.');
        }

        if ($apiToken->expires_at !== null && $apiToken->expires_at->isPast()) {
            $apiToken->delete();

            return $this->unauthorizedResponse('Authorization token has expired.');
        }

        $user = SdsUser::query()
            ->with('role')
            ->where('user_id', $apiToken->user_id)
            ->where('status_id', 1)
            ->where('is_deleted', 0)
            ->first();

        if ($user === null) {
            $apiToken->delete();

            return $this->unauthorizedResponse('User account is not active.');
        }

        if (!$this->isAdministrator($user) && $this->resolveUserCensusId($user) === null) {
            return $this->forbiddenResponse('User is not assigned to a school. Contact administrator.');
        }

        $apiToken->forceFill([
            'last_used_at' => now(),
        ])->save();

        $request->attributes->set('auth_user', $user);
        $request->attributes->set('auth_token', $apiToken);

        return $next($request);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = (string) $request->header('Authorization', '');

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        $token = trim((string) ($matches[1] ?? ''));

        return $token !== '' ? $token : null;
    }

    private function unauthorizedResponse(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], 401);
    }

    private function forbiddenResponse(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], 403);
    }
}
