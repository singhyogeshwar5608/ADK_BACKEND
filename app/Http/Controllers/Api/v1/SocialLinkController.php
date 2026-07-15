<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\SocialLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialLinkController extends Controller
{
    /**
     * Display a listing of social links (public).
     */
    public function index(): JsonResponse
    {
        $links = SocialLink::where('is_active', true)
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $links,
            'message' => 'Social links retrieved successfully'
        ]);
    }

    /**
     * Display all social links for admin panel (media-manager).
     */
    public function adminIndex(): JsonResponse
    {
        $links = SocialLink::orderBy('platform', 'asc')->get();

        return response()->json([
            'links' => $links,
        ]);
    }

    /**
     * Handle admin CRUD actions for social links (media-manager).
     */
    public function adminUpdate(Request $request): JsonResponse
    {
        $admin = $request->user();
        if ($admin->role !== 'ADMIN') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can perform this action.',
            ], 403);
        }

        $action = $request->input('action');

        try {
            switch ($action) {
                case 'update_all':
                    $links = $request->input('links', []);
                    foreach ($links as $linkData) {
                        if (isset($linkData['id'])) {
                            $link = SocialLink::find($linkData['id']);
                            if ($link) {
                                $link->update([
                                    'platform' => $linkData['platform'] ?? $link->platform,
                                    'url' => $linkData['url'] ?? $link->url,
                                ]);
                            }
                        }
                    }
                    return response()->json([
                        'success' => true,
                        'message' => 'All links updated successfully',
                    ]);

                case 'create':
                    $request->validate([
                        'platform' => 'required|string|max:50',
                        'url' => 'required|string|max:2048',
                    ]);

                    $existing = SocialLink::where('platform', $request->platform)->first();
                    if ($existing) {
                        $existing->update(['url' => $request->url]);
                    } else {
                        SocialLink::create([
                            'platform' => $request->platform,
                            'url' => $request->url,
                            'is_active' => true,
                        ]);
                    }

                    return response()->json([
                        'success' => true,
                        'message' => 'Link created/updated successfully',
                    ]);

                case 'delete':
                    $request->validate([
                        'id' => 'required|integer|exists:social_links,id',
                    ]);

                    SocialLink::destroy($request->id);

                    return response()->json([
                        'success' => true,
                        'message' => 'Link deleted successfully',
                    ]);

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid action',
                    ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
