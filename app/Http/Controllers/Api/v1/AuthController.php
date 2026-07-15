<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Member;
use App\Services\MemberPlacementService;
use App\Services\MemberStatsService;
use App\Services\ReferralService;
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

        // Validate referral code (required)
        if (!isset($data['referral_code']) || empty($data['referral_code'])) {
            return response()->json([
                'status' => false,
                'message' => 'Referral code is required'
            ], 400);
        }

        $referralCode = $data['referral_code'];
        
        // Debug logging
        \Log::info('Signup attempt with referral code: ' . $referralCode);
        
        // Check database connection
        try {
            \DB::connection()->getPdo();
            \Log::info('Database connection successful');
        } catch (\Exception $e) {
            \Log::error('Database connection failed: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Database connection error. Please try again later.',
                'error_type' => 'database_error'
            ], 500);
        }
        
        $referralUser = ReferralService::validateReferralCode($referralCode);
            
        \Log::info('Referral user search result: ' . ($referralUser ? 'Found' : 'Not found'));
        
        if ($referralUser) {
            \Log::info('Found referral user: ' . $referralUser->member_id . ' - ' . $referralUser->full_name);
        }

        if (!$referralUser) {
            \Log::warning('Invalid referral code attempted: ' . $referralCode);
            return response()->json([
                'status' => false,
                'message' => 'This referral code is not valid. Please check the link or contact the person who shared it with you.',
                'error_type' => 'invalid_referral_code',
                'debug_info' => [
                    'referral_code' => $referralCode,
                    'search_fields' => ['member_id', 'referral_code']
                ]
            ], 400);
        }

        if (Member::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Email already in use',
            ]);
        }

        if (!empty($data['phone']) && Member::where('phone', $data['phone'])->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'This phone number is already registered with another account.',
            ]);
        }

        // Same binary placement as admin API: L/R paths (Tree::childPath), not legacy id concatenation.
        $sponsorIdentifier = $data['sponsor_id'] ?? $referralUser->member_id;
        $leg = strtoupper((string) ($data['leg'] ?? 'LEFT'));
        if (!in_array($leg, ['LEFT', 'RIGHT'], true)) {
            $leg = 'LEFT';
        }

        try {
            $placement = MemberPlacementService::resolve($sponsorIdentifier, $leg);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to add you to the sponsor\'s binary tree (leg may be full).',
                'errors' => $e->errors(),
            ], 422);
        }

        $parent = $placement['sponsor'];
        $originalSponsor = $placement['referrer'] ?? $parent;
        if (!$parent) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid placement: sponsor could not be resolved.',
            ], 422);
        }

        $serialNo = (Member::max('serial_no') ?? 0) + 1;

        $member = Member::create([
            'member_id' => IdGenerator::memberId(),
            'sponsor_id' => $originalSponsor->id,
            'leg' => $placement['leg'],
            'placement_path' => $placement['path'],
            'depth' => $placement['depth'],
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'profile_image' => $data['profile_image'] ?? null,
            'role' => $data['role'] ?? 'MEMBER',
            'password_hash' => Hash::make($data['password']),
            'status' => 'PENDING',
            'referred_by' => $referralUser->member_id,
            'serial_no' => $serialNo,
        ]);

        $this->statsService->handleNewMember($member);

        $tokens = $this->issueTokens($member);

        return response()->json([
            'status' => true,
            'message' => "Account created successfully! Your Member ID is {$member->member_id}. Please save it for future reference.",
            'member' => $member,
            'member_id' => $member->member_id,
            'tokens' => $tokens
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $wantsAdmin = $request->boolean('login_as_admin');

        $member = Member::where('email', $credentials['email'])->first();

        if (!$member || !Hash::check($credentials['password'], $member->password_hash)) {
            throw ValidationException::withMessages([
                'email' => 'Invalid credentials',
            ]);
        }

        if ($wantsAdmin && $member->role !== 'ADMIN') {
            throw ValidationException::withMessages([
                'email' => 'This account does not have administrator access.',
            ]);
        }

        if (!$wantsAdmin && $member->role === 'ADMIN') {
            throw ValidationException::withMessages([
                'email' => 'Please enable the admin login option to sign in as an administrator.',
            ]);
        }

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

        $tokens = $this->issueTokens($member);

        return response()->json(array_merge(['member' => $member], $tokens));
    }

    public function me(): JsonResponse
    {
        $member = auth()->user();
        if (! $member instanceof Member) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        \Log::info('BV Values Debug', [
            'member_id' => $member->member_id,
            'bv_left_leg' => $member->bv_left_leg,
            'bv_right_leg' => $member->bv_right_leg,
            'total_matched_bv' => $member->total_matched_bv,
        ]);
        
        // Direct income from income_ledger (legacy); matching uses income_transactions below
        $directIncome = $member->incomeLedger()
            ->where('type', 'direct')
            ->sum('amount') ?? 0;

        $selfPurchaseIncome = (float) ($member->incomeTransactions()
            ->where('type', 'SELF')
            ->sum('amount') ?? 0);

        $selfRepurchaseIncome = (float) ($member->incomeTransactions()
            ->where('type', 'REPURCHASE_SELF')
            ->sum('amount') ?? 0);

        $sponsorIncome = (float) ($member->incomeTransactions()
            ->where('type', 'SPONSOR')
            ->sum('amount') ?? 0);

        $sponsorAwardKitRepurchaseIncome = (float) ($member->incomeTransactions()
            ->where('type', 'SPONSOR_AWARD')
            ->sum('amount') ?? 0);

        $matchingIncomeFromTransactions = (float) ($member->incomeTransactions()
            ->where('type', 'MATCHING')
            ->sum('amount') ?? 0);

        $repurchaseMatchingIncome = (float) ($member->incomeTransactions()
            ->where('type', 'REPURCHASE_MATCHING')
            ->sum('amount') ?? 0);

        $tdsIncome = (float) ($member->incomeTransactions()
            ->where('type', 'TDS_INCOME')
            ->sum('amount') ?? 0);
        
        $response = [
            'member' => [
                ...$member->toArray(),
                'wallet' => [
                    'balance' => $member->wallet_balance,
                    'totalEarned' => $member->wallet_total_earned,
                ],
                'income' => [
                    'direct' => $directIncome,
                    'matching' => $matchingIncomeFromTransactions,
                    'weekly' => $member->weekly_income,
                    'selfPurchase' => $selfPurchaseIncome,
                    'selfRepurchaseIncome' => $selfRepurchaseIncome,
                    'sponsorIncome' => $sponsorIncome,
                    'sponsorAwardKitRepurchaseIncome' => $sponsorAwardKitRepurchaseIncome,
                    'repurchaseMatchingIncome' => $repurchaseMatchingIncome,
                    'downlineMonthlySponsor' => $member->downline_monthly_sponsor_income,
                    'tdsIncome' => $tdsIncome,
                ],
                'matchingPairs' => [
                    'leftLeg' => $member->bv_left_leg,
                    'rightLeg' => $member->bv_right_leg,
                    'totalMatched' => $member->total_matched_bv,
                ],
                'stats' => [
                    'directRefs' => $member->stats_direct_refs ?? 0,
                    'teamSize' => $member->stats_team_size ?? 0,
                ],
            ],
        ];
        
        \Log::info('API Response', $response);
        
        return response()->json($response);
    }

    public function teamStats(): JsonResponse
    {
        $member = auth()->user();

        if (!$member->placement_path) {
            return response()->json([
                'newThisWeek' => 0,
                'newThisMonth' => 0,
                'totalActive' => 0,
                'pendingKyc' => 0,
            ]);
        }

        // Get all members in the user's binary tree (downline by placement_path)
        $teamQuery = Member::query()
            ->where('placement_path', 'like', $member->placement_path . '.%');
        
        // Calculate new members this week (from Monday to today)
        $startOfWeek = now()->startOfWeek();
        $newThisWeek = $teamQuery->clone()
            ->where('created_at', '>=', $startOfWeek)
            ->where('status', 'ACTIVE')
            ->count();
            
        // Calculate new members this month
        $startOfMonth = now()->startOfMonth();
        $newThisMonth = $teamQuery->clone()
            ->where('created_at', '>=', $startOfMonth)
            ->where('status', 'ACTIVE')
            ->count();
            
        // Total active members in team (entire downline)
        $totalActive = $teamQuery->clone()
            ->where('status', 'ACTIVE')
            ->count();
        
        // Calculate pending KYC (members who haven't made any purchase/payment)
        $pendingKyc = $teamQuery->clone()
            ->where('status', 'ACTIVE')
            ->where(function($query) {
                $query->whereNull('first_purchase_at')
                      ->orWhere('wallet_total_earned', '=', 0);
            })
            ->count();
        
        return response()->json([
            'newThisWeek' => $newThisWeek,
            'newThisMonth' => $newThisMonth,
            'totalActive' => $totalActive,
            'pendingKyc' => $pendingKyc,
        ]);
    }

    public function updateProfile(): JsonResponse
    {
        $member = auth()->user();
        if (!$member) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $data = request()->validate([
            'fullName' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:members,email,' . $member->id,
            'phone' => 'sometimes|string|max:20',
            'address' => 'sometimes|string|max:255',
            'landmark' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:100',
            'state' => 'sometimes|string|max:100',
            'profileImage' => 'sometimes|string|max:500',
            'qrCodeUrl' => 'sometimes|string|max:500',
        ]);

        $member->update([
            'full_name' => $data['fullName'] ?? $member->full_name,
            'email' => $data['email'] ?? $member->email,
            'phone' => $data['phone'] ?? $member->phone,
            'address' => $data['address'] ?? $member->address,
            'landmark' => $data['landmark'] ?? $member->landmark,
            'city' => $data['city'] ?? $member->city,
            'state' => $data['state'] ?? $member->state,
            'profile_image' => $data['profileImage'] ?? $member->profile_image,
            'qr_code_url' => $data['qrCodeUrl'] ?? $member->qr_code_url,
        ]);

        return response()->json(['member' => $member]);
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
