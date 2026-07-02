<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PincodeController extends Controller
{
    public function show(Request $request, string $pincode): JsonResponse
    {
        $corsHeaders = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
        ];

        // Handle OPTIONS preflight request
        if ($request->isMethod('OPTIONS')) {
            return response()->json(null, 200, $corsHeaders);
        }

        $pincode = trim($pincode);
        if (!preg_match('/^\d{6}$/', $pincode)) {
            return response()->json([
                'message' => 'Invalid pincode',
            ], 422, $corsHeaders);
        }

        try {
            $url = "https://api.postalpincode.in/pincode/{$pincode}";
            $res = Http::withoutVerifying()
                ->timeout(10)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => 'ADK-App/1.0',
                ])
                ->get($url);

            if (!$res->successful()) {
                return response()->json([
                    'message' => 'Failed to fetch pincode details',
                    'status' => $res->status(),
                    'body' => $res->body(),
                ], 502, $corsHeaders);
            }

            $payload = $res->json();
            // India Post API returns an array with one object.
            $item = is_array($payload) && isset($payload[0]) && is_array($payload[0]) ? $payload[0] : null;
            $postOffices = $item['PostOffice'] ?? null;

            if (!is_array($postOffices) || count($postOffices) === 0) {
                return response()->json([
                    'message' => 'No data found for pincode',
                ], 404, $corsHeaders);
            }

            $first = $postOffices[0];

            return response()->json([
                'pincode' => $pincode,
                'city' => (string) ($first['District'] ?? ''),
                'state' => (string) ($first['State'] ?? ''),
                'country' => (string) ($first['Country'] ?? 'India'),
                'postOffices' => $postOffices,
            ], 200, $corsHeaders);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error while fetching pincode details',
                'error' => $e->getMessage(),
            ], 500, $corsHeaders);
        }
    }
}
