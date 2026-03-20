<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\DashboardMetricsRequest;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function dashboard(DashboardMetricsRequest $request): JsonResponse
    {
        $metrics = ReportService::dashboardMetrics((int) $request->input('range', 30));

        return response()->json($metrics);
    }
}
