<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\MemberIndexRequest;
use App\Http\Requests\Member\MemberStoreRequest;
use App\Http\Requests\Member\MemberUpdateRequest;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use App\Services\MemberPlacementService;
use App\Services\MemberStatsService;
use App\Support\IdGenerator;
use App\Support\Tree;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MemberController extends Controller
{
    public function __construct(private readonly MemberStatsService $statsService)
    {
    }

    public function index(MemberIndexRequest $request): JsonResponse
    {
        $query = Member::query();

        $query->select('members.*')
            ->selectSub(function ($sub) {
                $sub->from('members as descendants_left')
                    ->selectRaw('COUNT(*)')
                    ->whereRaw("descendants_left.placement_path LIKE CONCAT(members.placement_path, '.L%')");
            }, 'left_team_count')
            ->selectSub(function ($sub) {
                $sub->from('members as descendants_right')
                    ->selectRaw('COUNT(*)')
                    ->whereRaw("descendants_right.placement_path LIKE CONCAT(members.placement_path, '.R%')");
            }, 'right_team_count')
            ->selectSub(function ($sub) {
                $sub->from('members as child_left')
                    ->select('child_left.member_id')
                    ->whereRaw("child_left.placement_path = CONCAT(members.placement_path, '.L')")
                    ->limit(1);
            }, 'left_child_member_id')
            ->selectSub(function ($sub) {
                $sub->from('members as child_right')
                    ->select('child_right.member_id')
                    ->whereRaw("child_right.placement_path = CONCAT(members.placement_path, '.R')")
                    ->limit(1);
            }, 'right_child_member_id');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('full_name', 'like', "%{$search}%")
                    ->orWhere('member_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $query->orderByRaw("FIELD(role, 'ADMIN') DESC")->orderBy('serial_no')->with('sponsor:id,member_id');

        $limit = (int) $request->input('limit', 10);
        $page = (int) $request->input('page', 1);

        $paginator = $query->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'data' => MemberResource::collection($paginator->items()),
            'meta' => [
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'pages' => $paginator->lastPage(),
            ],
        ]);
    }

    public function publicList(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);

        $limit = $validated['limit'] ?? 50;
        $search = $validated['search'] ?? null;

        $members = Member::query()
            ->select(['id', 'member_id', 'full_name', 'role', 'status', 'profile_image', 'stats_direct_refs', 'bv_total', 'bv_left_leg', 'bv_right_leg', 'leg', 'address', 'phone', 'email', 'created_at'])
            ->when($search, function (Builder $query) use ($search) {
                $query->where(function (Builder $builder) use ($search) {
                    $builder
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('member_id', 'like', "%{$search}%");
                });
            })
            ->orderBy('full_name')
            ->limit($limit)
            ->get()
            ->map(fn (Member $member) => [
                'id' => $member->id,
                'memberId' => $member->member_id,
                'name' => $member->full_name,
                'role' => $member->role,
                'status' => $member->status,
                'profileImage' => $member->profile_image,
                'teamSize' => $member->stats_direct_refs ?? 0,
                'totalBv' => (float) $member->bv_total,
                'bvLeftLeg' => (float) $member->bv_left_leg,
                'bvRightLeg' => (float) $member->bv_right_leg,
                'weakLeg' => $member->leg ?? 'NONE',
                'location' => $member->address ?? 'Not specified',
                'contactEmail' => $member->email,
                'contactPhone' => $member->phone ?? 'Not provided',
                'createdAt' => $member->created_at->toISOString(),
            ]);

        return response()->json([
            'members' => $members,
            'meta' => [
                'count' => $members->count(),
            ],
        ]);
    }

    public function show(string $memberId): JsonResponse
    {
        $member = $this->findMember($memberId);

        return response()->json([
            'member' => MemberResource::make($member->loadMissing('sponsor:id,member_id')),
        ]);
    }

    public function store(MemberStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (!empty($data['phone']) && Member::where('phone', $data['phone'])->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'This phone number is already registered with another account.',
            ]);
        }

        $placement = MemberPlacementService::resolve($data['sponsor_id'], $data['leg']);

        $serialNo = (Member::max('serial_no') ?? 0) + 1;

        $member = Member::create([
            'member_id' => IdGenerator::memberId(),
            'sponsor_id' => $placement['referrer']?->id ?? $placement['sponsor']?->id,
            'referred_by' => $placement['referrer']?->member_id,
            'leg' => $placement['leg'],
            'placement_path' => $placement['path'],
            'depth' => $placement['depth'],
            'full_name' => $data['full_name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'],
            'profile_image' => $data['profile_image'] ?? null,
            'role' => 'MEMBER',
            'password_hash' => Hash::make($data['password']),
            'status' => 'PENDING',
            'type' => $data['type'] ?? 'USER',
            'serial_no' => $serialNo,
        ]);

        $this->statsService->handleNewMember($member);

        return response()->json([
            'member' => MemberResource::make($member->loadMissing('sponsor:id,member_id')),
        ], 201);
    }

    public function update(MemberUpdateRequest $request, string $memberId): JsonResponse
    {
        $member = $this->findMember($memberId);

        $data = $request->validated();

        if (isset($data['email'])) {
            $emailExists = Member::query()
                ->where('email', $data['email'])
                ->where('id', '!=', $member->id)
                ->exists();

            if ($emailExists) {
                throw ValidationException::withMessages([
                    'email' => 'Email already in use by another member.',
                ]);
            }
        }

        if (!empty($data['phone'])) {
            $phoneExists = Member::query()
                ->where('phone', $data['phone'])
                ->where('id', '!=', $member->id)
                ->exists();

            if ($phoneExists) {
                throw ValidationException::withMessages([
                    'phone' => 'This phone number is already in use by another member.',
                ]);
            }
        }

        $oldStatus = $member->status;
        $oldSponsorId = $member->sponsor_id;
        $oldPlacementPath = $member->placement_path;

        // Sponsor re-assignment: resolve the new placement BEFORE persisting so
        // validation errors abort early. The actual attributes are applied after fill().
        $sponsorChanged = false;
        $placement = null;
        $currentSponsorMemberId = $member->sponsor_id
            ? (string) ($member->sponsor?->member_id ?? '')
            : '';

        if (isset($data['sponsor_id']) && trim((string) $data['sponsor_id']) !== ''
            && trim((string) $data['sponsor_id']) !== $currentSponsorMemberId) {
            $sponsorChanged = true;
            $newSponsorIdentifier = trim((string) $data['sponsor_id']);

            // Cycle guards: cannot be own sponsor and cannot be placed under own downline.
            $newSponsorMember = Member::query()
                ->where('member_id', $newSponsorIdentifier)
                ->orWhere('id', is_numeric($newSponsorIdentifier) ? (int) $newSponsorIdentifier : 0)
                ->first();

            if (!$newSponsorMember) {
                throw ValidationException::withMessages([
                    'sponsor_id' => 'Sponsor not found.',
                ]);
            }

            if ((int) $newSponsorMember->id === (int) $member->id) {
                throw ValidationException::withMessages([
                    'sponsor_id' => 'A member cannot be their own sponsor.',
                ]);
            }

            if ($member->placement_path && $newSponsorMember->placement_path
                && str_starts_with($newSponsorMember->placement_path, $member->placement_path . '.')) {
                throw ValidationException::withMessages([
                    'sponsor_id' => 'A member cannot be moved under their own downline.',
                ]);
            }

            $preferredLeg = strtoupper((string) ($data['leg'] ?? $member->leg ?? 'LEFT'));

            try {
                $placement = MemberPlacementService::resolve($newSponsorIdentifier, $preferredLeg, $member->id);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages([
                    'sponsor_id' => 'Unable to place this member under the selected sponsor: '
                        . implode(' ', array_merge(...array_values($e->errors()))),
                ]);
            }
        }

        $member->fill([
            'full_name' => $data['full_name'] ?? $member->full_name,
            'email' => isset($data['email']) ? strtolower($data['email']) : $member->email,
            'phone' => $data['phone'] ?? $member->phone,
            'status' => $data['status'] ?? $member->status,
            'type' => $data['type'] ?? $member->type,
            'leg' => $data['leg'] ?? $member->leg,
            'profile_image' => array_key_exists('profile_image', $data)
                ? $data['profile_image']
                : $member->profile_image,
            // KYC fields
            'bank_account_number' => array_key_exists('bank_account_number', $data)
                ? $data['bank_account_number'] : $member->bank_account_number,
            'bank_account_image' => array_key_exists('bank_account_image', $data)
                ? $data['bank_account_image'] : $member->bank_account_image,
            'pan_number' => array_key_exists('pan_number', $data)
                ? $data['pan_number'] : $member->pan_number,
            'pan_image' => array_key_exists('pan_image', $data)
                ? $data['pan_image'] : $member->pan_image,
            'aadhar_number' => array_key_exists('aadhar_number', $data)
                ? $data['aadhar_number'] : $member->aadhar_number,
            'aadhar_image' => array_key_exists('aadhar_image', $data)
                ? $data['aadhar_image'] : $member->aadhar_image,
            'aadhar_back_image' => array_key_exists('aadhar_back_image', $data)
                ? $data['aadhar_back_image'] : $member->aadhar_back_image,
            // Nominee fields
            'nominee_name' => array_key_exists('nominee_name', $data)
                ? $data['nominee_name'] : $member->nominee_name,
            'nominee_aadhar_number' => array_key_exists('nominee_aadhar_number', $data)
                ? $data['nominee_aadhar_number'] : $member->nominee_aadhar_number,
            'nominee_aadhar_image' => array_key_exists('nominee_aadhar_image', $data)
                ? $data['nominee_aadhar_image'] : $member->nominee_aadhar_image,
            'nominee_aadhar_back_image' => array_key_exists('nominee_aadhar_back_image', $data)
                ? $data['nominee_aadhar_back_image'] : $member->nominee_aadhar_back_image,
        ]);

        // Apply resolved placement last so it wins over the raw leg field from the form.
        if ($sponsorChanged && $placement !== null) {
            $member->sponsor_id = $placement['referrer']?->id ?? $placement['sponsor']?->id;
            $member->referred_by = $placement['referrer']?->member_id;
            $member->leg = $placement['leg'];
            $member->placement_path = $placement['path'];
            $member->depth = $placement['depth'];
        }

        $member->save();

        // Update password only when a new (non-empty) one is provided
        if (!empty($data['password'])) {
            $member->password_hash = Hash::make($data['password']);
            $member->save();
        }

        // Update QR code image separately (not in fillable)
        if (array_key_exists('qr_code_image', $data)) {
            $member->qr_code_image = $data['qr_code_image'];
            $member->save();
        }

        // A sponsor change moves the whole subtree: rewrite every descendant's
        // placement path, reconcile team-size counters, and refresh both the old
        // and the new sponsor's referral counts so income/tree data follow along.
        if ($sponsorChanged) {
            if ($oldPlacementPath) {
                $oldPrefix = $oldPlacementPath . '.';
                Member::query()
                    ->where('placement_path', 'like', $oldPrefix . '%')
                    ->orderBy('depth')
                    ->get()
                    ->each(function (Member $descendant) use ($oldPrefix, $member) {
                        $descendant->placement_path = $member->placement_path
                            . substr($descendant->placement_path, strlen($oldPrefix));
                        $descendant->depth = Tree::depthFromPath($descendant->placement_path);
                        $descendant->save();
                    });
            }

            $this->reconcileTeamSize($oldPlacementPath, $member->placement_path);

            if ($oldSponsorId && (int) $oldSponsorId !== (int) $member->sponsor_id) {
                $this->recomputeSponsorReferralCounts($oldSponsorId);
            }
            if ($member->sponsor_id) {
                $this->recomputeSponsorReferralCounts($member->sponsor_id);
            }
        }

        // If member was just activated and already has a first purchase,
        // ensure the sponsor's direct referral counts include this member
        if ($oldStatus !== 'ACTIVE' && $member->status === 'ACTIVE' && $member->first_purchase_at) {
            $sponsor = null;
            if ($member->referred_by) {
                $sponsor = Member::where('member_id', $member->referred_by)->first();
            } elseif ($member->sponsor_id) {
                $sponsor = Member::find($member->sponsor_id);
            }

            if ($sponsor) {
                $activeDirectReferrals = Member::where(function ($q) use ($sponsor) {
                        $q->where('referred_by', $sponsor->member_id)
                          ->orWhere('sponsor_id', $sponsor->id);
                    })
                    ->where('status', 'ACTIVE')
                    ->whereNotNull('first_purchase_at')
                    ->count();

                $sponsor->direct_referrals_count = $activeDirectReferrals;

                // Family Tour Award: only count members who have at least one order ≥ 5000 BV
                $familyTourCount = Member::where(function ($q) use ($sponsor) {
                        $q->where('referred_by', $sponsor->member_id)
                          ->orWhere('sponsor_id', $sponsor->id);
                    })
                    ->where('status', 'ACTIVE')
                    ->whereExists(function ($q) {
                        $q->selectRaw(1)
                          ->from('orders')
                          ->whereColumn('orders.member_id', 'members.id')
                          ->where('orders.total_bv', '>=', 5000);
                    })
                    ->count();

                if ($sponsor->stats_direct_refs < $familyTourCount) {
                    $sponsor->stats_direct_refs = $familyTourCount;
                }

                $sponsor->save();
            }
        }

        return response()->json([
            'member' => MemberResource::make($member->fresh('sponsor:id,member_id')),
        ]);
    }

    public function destroy(string $memberId): JsonResponse
    {
        $member = $this->findMember($memberId);
        $member->delete();

        return response()->json([
            'member' => MemberResource::make($member),
        ]);
    }

    public function tree(Request $request, string $memberId): JsonResponse
    {
        $validated = $request->validate([
            'depth' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);

        $root = $this->findMember($memberId);

        $auth = $request->user();
        if (! $auth instanceof Member) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($denied = $this->authorizeBinaryTreeAccess($auth, $root)) {
            return $denied;
        }

        $isAdmin = strtoupper(trim((string) Member::query()
            ->where('id', $auth->id)
            ->value('role'))) === 'ADMIN';

        $requestedDepth = (int) ($validated['depth'] ?? 3);
        $depthLimit = max(1, min($requestedDepth, 500));

        $maxDepth = $root->depth + $depthLimit;

        $nodeLimit = 200_000;

        $nodes = Member::query()
            ->where('placement_path', 'like', $root->placement_path . '%')
            ->where('depth', '<=', $maxDepth)
            ->orderBy('depth')
            ->limit($nodeLimit + 1)
            ->with('sponsor:id,member_id')
            ->get();

        $truncated = $nodes->count() > $nodeLimit;
        if ($truncated) {
            $nodes = $nodes->take($nodeLimit);
        }

        $memberIdsForLookup = $nodes->pluck('id')->push($root->id)->unique()->values()->all();
        MemberResource::beginBinaryTreePaidLookup($memberIdsForLookup);
        try {
            return response()->json([
                'root' => MemberResource::make($root->loadMissing('sponsor:id,member_id')),
                'nodes' => MemberResource::collection($nodes),
                'meta' => [
                    'depthLimit' => $depthLimit,
                    'count' => $nodes->count(),
                    'nodeLimit' => $nodeLimit,
                    'truncated' => $truncated,
                ],
            ]);
        } finally {
            MemberResource::endBinaryTreePaidLookup();
        }
    }

    public function team(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            \Log::info('Team API: No authenticated user');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $limit = (int) $request->query('limit', 1000);
        $limit = max(1, min($limit, 1000));

        // Debug logging
        \Log::info('Team Members Debug', [
            'user_id' => $user->id,
            'user_member_id' => $user->member_id,
            'user_name' => $user->full_name,
        ]);

        // Get only DIRECT members (referred by this member OR directly placed under them)
        $teamMembers = Member::query()
            ->where(function (Builder $q) use ($user) {
                $q->where('referred_by', $user->member_id)
                  ->orWhere('sponsor_id', $user->id);
            })
            ->where('id', '!=', $user->id)
            ->orderBy('serial_no', 'asc')
            ->limit($limit)
            ->get();

        \Log::info('Team Members Found', [
            'count' => $teamMembers->count(),
            'members' => $teamMembers->pluck('full_name', 'member_id')->toArray(),
        ]);

        return response()->json([
            'members' => MemberResource::collection($teamMembers),
            'meta' => [
                'count' => $teamMembers->count(),
                'limit' => $limit,
            ],
        ]);
    }

    public function teamTest(Request $request): JsonResponse
    {
        \Log::info('Team Test API called');
        return response()->json(['message' => 'Team API is working', 'timestamp' => now()]);
    }

    private function findMember(string $identifier): Member
    {
        $query = Member::query()->with('sponsor:id,member_id');

        if (strtolower($identifier) === 'root') {
            return $query
                ->whereNull('sponsor_id')
                ->orderBy('id')
                ->firstOrFail();
        }

        return $query
            ->where(function (Builder $builder) use ($identifier) {
                $builder->where('member_id', $identifier)
                    ->orWhere('id', $identifier);
            })
            ->firstOrFail();
    }

    /**
     * Members may only load their own binary tree and only while ACTIVE.
     * Admins may load any member's tree (admin panel / support).
     *
     * Always re-read status from the database so suspended accounts cannot use a stale in-memory model.
     */
    private function authorizeBinaryTreeAccess(Member $auth, Member $root): ?JsonResponse
    {
        $authFresh = Member::query()
            ->select(['id', 'role', 'status'])
            ->where('id', $auth->id)
            ->first();

        if (! $authFresh) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $isAdmin = strtoupper(trim((string) $authFresh->role)) === 'ADMIN';

        if (! $isAdmin) {
            if ((int) $root->id !== (int) $authFresh->id) {
                return response()->json([
                    'message' => 'You do not have permission to view this tree.',
                ], 403);
            }

            if (! $authFresh->hasActiveAccountStanding()) {
                return response()->json([
                    'message' => 'Binary tree is not available while your account is suspended or not active.',
                ], 403);
            }
        }

        return null;
    }

    /**
     * Move the stored team-size contribution from the old ancestor paths to the new ones.
     * Common ancestors cancel out (decrement then increment nets to zero).
     */
    private function reconcileTeamSize(?string $oldPath, string $newPath): void
    {
        $oldAncestors = $oldPath ? $this->ancestorPaths($oldPath) : [];
        $newAncestors = $this->ancestorPaths($newPath);

        if (!empty($oldAncestors)) {
            Member::query()->whereIn('placement_path', $oldAncestors)->decrement('stats_team_size');
        }
        if (!empty($newAncestors)) {
            Member::query()->whereIn('placement_path', $newAncestors)->increment('stats_team_size');
        }
    }

    /** Every ancestor placement path of a member path (excluding the member itself). */
    private function ancestorPaths(string $path): array
    {
        $segments = explode('.', $path);
        $paths = [];

        while (count($segments) > 1) {
            array_pop($segments);
            $paths[] = implode('.', $segments);
        }

        return $paths;
    }

    /**
     * Rebuild a sponsor's direct-referral counters straight from the database
     * (used when a member is moved to a different sponsor so income/tree
     * numbers follow the new relationship).
     */
    private function recomputeSponsorReferralCounts(?int $sponsorId): void
    {
        if (!$sponsorId) {
            return;
        }

        $sponsor = Member::find($sponsorId);
        if (!$sponsor) {
            return;
        }

        $activeDirectReferrals = Member::where(function ($q) use ($sponsor) {
                $q->where('referred_by', $sponsor->member_id)
                  ->orWhere('sponsor_id', $sponsor->id);
            })
            ->where('status', 'ACTIVE')
            ->whereNotNull('first_purchase_at')
            ->count();

        $familyTourCount = Member::where(function ($q) use ($sponsor) {
                $q->where('referred_by', $sponsor->member_id)
                  ->orWhere('sponsor_id', $sponsor->id);
            })
            ->where('status', 'ACTIVE')
            ->whereExists(function ($q) {
                $q->selectRaw(1)
                  ->from('orders')
                  ->whereColumn('orders.member_id', 'members.id')
                  ->where('orders.total_bv', '>=', 5000);
            })
            ->count();

        $sponsor->direct_referrals_count = $activeDirectReferrals;
        $sponsor->stats_direct_refs = $familyTourCount;
        $sponsor->reward_eligible = $activeDirectReferrals >= 30;
        $sponsor->save();
    }
}
