<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\AuditTargetType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexPlatformAnalyticsRequest;
use App\Services\Analytics\AnalyticsService;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * Render the platform-wide analytics page.
     *
     * Authorization is enforced by the route middleware:
     * permission:admin.access and permission:platform_analytics.view.
     */
    public function index(IndexPlatformAnalyticsRequest $request): Response
    {
        $window = $request->analyticsWindow();
        $from = $window['from'];
        $to = $window['to'];

        $metrics = $this->analyticsService->platformMetrics($from, $to);
        $topCategories = $this->analyticsService->platformTopCategories($from, $to);
        $trend = $this->analyticsService->platformTrend($from, $to);

        $this->recordView([
            'from' => $from?->format('Y-m-d'),
            'to' => $to?->format('Y-m-d'),
            'window' => $window['preset'] === 'all' ? 'all_time' : 'bounded',
        ]);

        return Inertia::render('admin/analytics', [
            'window' => [
                'from' => $from?->format('Y-m-d'),
                'to' => $to?->format('Y-m-d'),
                'preset' => $window['preset'],
            ],
            'summary' => $metrics['summary'],
            'jobsByStatus' => $metrics['jobsByStatus'],
            'applicationsByStatus' => $metrics['applicationsByStatus'],
            'employersByStatus' => $metrics['employersByStatus'],
            'topCategories' => $topCategories,
            'trend' => $trend,
            'definitions' => $this->analyticsService->metricDefinitions('platform'),
        ]);
    }

    /**
     * Record that an administrator read the platform analytics.
     *
     * Read-path writes are fail-soft: a logging problem must never break the
     * analytics page.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function recordView(array $metadata): void
    {
        try {
            $this->auditLogService->recordWithoutModel(
                AuditAction::AnalyticsViewed,
                AuditTargetType::Analytics->value,
                null,
                'platform',
                ['scope' => 'platform', ...$metadata],
            );
        } catch (\Throwable $exception) {
            Log::error('Analytics view could not be recorded.', [
                'scope' => 'platform',
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
