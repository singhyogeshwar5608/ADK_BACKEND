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
                    ->whereColumn('child_left.sponsor_id', 'members.id')
                    ->where('child_left.leg', 'LEFT')
                    ->orderBy('child_left.id')
                    ->limit(1);
            }, 'left_child_member_id')
            ->selectSub(function ($sub) {
                $sub->from('members as child_right')
                    ->select('child_right.member_id')
                    ->whereColumn('child_right.sponsor_id', 'members.id')
                    ->where('child_right.leg', 'RIGHT')
                    ->orderBy('child_right.id')
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

        $query->orderByDesc('created_at')->with('sponsor:id,member_id');

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

        $placement = MemberPlacementService::resolve($data['sponsor_id'], $data['leg']);

        $member = Member::create([
            'member_id' => IdGenerator::memberId(),
            'sponsor_id' => $placement['sponsor']?->id,
            'leg' => $placement['leg'],
            'placement_path' => $placement['path'],
            'depth' => $placement['depth'],
            'full_name' => $data['full_name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'profile_image' => $data['profile_image'] ?? null,
            'role' => 'MEMBER',
            'password_hash' => Hash::make($data['password']),
            'status' => 'ACTIVE',
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

        $member->fill([
            'full_name' => $data['full_name'] ?? $member->full_name,
            'email' => isset($data['email']) ? strtolower($data['email']) : $member->email,
            'phone' => $data['phone'] ?? $member->phone,
            'status' => $data['status'] ?? $member->status,
            'leg' => $data['leg'] ?? $member->leg,
            'profile_image' => array_key_exists('profile_image', $data)
                ? $data['profile_image']
                : $member->profile_image,
        ])->save();

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
            'depth' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ]);

        $depthLimit = $validated['depth'] ?? 3;
        $root = $this->findMember($memberId);

        $maxDepth = $root->depth + $depthLimit;

        $nodes = Member::query()
            ->where('placement_path', 'like', $root->placement_path . '%')
            ->where('depth', '<=', $maxDepth)
            ->orderBy('depth')
            ->limit(1500)
            ->with('sponsor:id,member_id')
            ->get();

        return response()->json([
            'root' => MemberResource::make($root->loadMissing('sponsor:id,member_id')),
            'nodes' => MemberResource::collection($nodes),
            'meta' => [
                'depthLimit' => $depthLimit,
                'count' => $nodes->count(),
            ],
        ]);
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
}
