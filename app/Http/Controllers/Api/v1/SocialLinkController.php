<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\SocialLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialLinkController extends Controller
{
    /**
     * Display a listing of social links.
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
}
