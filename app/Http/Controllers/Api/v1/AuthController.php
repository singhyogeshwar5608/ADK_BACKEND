<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Member;
use App\Services\MemberPlacementService;
use App\Services\MemberStatsService;
use App\Support\IdGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly MemberStatsService $statsService)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (Member::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Email already in use',
            ]);
        }

        $placement = MemberPlacementService::resolve($data['sponsor_id'] ?? null, $data['leg'] ?? null);

        $member = Member::create([
            'member_id' => IdGenerator::memberId(),
            'sponsor_id' => $placement['sponsor']?->id,
            'leg' => $placement['leg'],
            'placement_path' => $placement['path'],
            'depth' => $placement['depth'],
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'profile_image' => $data['profile_image'] ?? null,
            'role' => $data['role'] ?? 'MEMBER',
            'password_hash' => Hash::make($data['password']),
            'status' => 'ACTIVE',
        ]);

        $this->statsService->handleNewMember($member);

        $tokens = $this->issueTokens($member);

        return response()->json(array_merge(['member' => $member], $tokens), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $member = Member::where('email', $credentials['email'])->first();

        if (!$member || !Hash::check($credentials['password'], $member->password_hash)) {
            throw ValidationException::withMessages([
                'email' => 'Invalid credentials',
            ]);
        }

        $member->tokens()->delete();
        $tokens = $this->issueTokens($member);

        return response()->json(array_merge(['member' => $member], $tokens));
    }

    public function refresh(RefreshRequest $request): JsonResponse
    {
        $token = $request->string('refresh_token');
        $memberId = cache()->get($this->refreshCacheKey($token));

        if (!$memberId) {
            throw ValidationException::withMessages([
                'refresh_token' => 'Invalid refresh token',
            ]);
        }

        $member = Member::find($memberId);
        if (!$member) {
            throw ValidationException::withMessages([
                'refresh_token' => 'Member not found',
            ]);
        }

        $member->tokens()->delete();
        $tokens = $this->issueTokens($member);

        return response()->json(array_merge(['member' => $member], $tokens));
    }

    public function me(): JsonResponse
    {
        return response()->json([
            'member' => auth()->user(),
        ]);
    }

    public function logout(): JsonResponse
    {
        $user = auth()->user();
        if ($user) {
            $user->currentAccessToken()?->delete();
        }
        return response()->json(['message' => 'Logged out']);
    }

    private function issueTokens(Member $member): array
    {
        $accessToken = $member->createToken('access-token', ['*'])->plainTextToken;
        $refreshToken = bin2hex(random_bytes(40));

        cache()->put($this->refreshCacheKey($refreshToken), $member->id, now()->addDays(7));

        return [
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
        ];
    }

    private function refreshCacheKey(string $token): string
    {
        return 'refresh_token:' . $token;
    }
}
