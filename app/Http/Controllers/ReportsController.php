<?php

namespace App\Http\Controllers;

use App\Models\{
    User, Workspace, Document, DocumentVersion, 
    AuditLog, RetentionPolicy, LegalHold,
    DisposalRecord, AccessRequest, Signature
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ReportsController extends Controller
{
    /**
     * Display reports dashboard
     */
    public function index()
    {
        return view('reports.index', [
            'reports' => [
                'user_activity' => [
                    'title' => 'User Activity Report',
                    'description' => 'Track user actions and system usage patterns',
                    'icon' => 'users'
                ],
                'storage_usage' => [
                    'title' => 'Storage Usage Report',
                    'description' => 'Analyze storage consumption across workspaces',
                    'icon' => 'database'
                ],
                'compliance_status' => [
                    'title' => 'Compliance Status Report',
                    'description' => 'Monitor regulatory compliance and retention policies',
                    'icon' => 'shield-check'
                ]
            ]
        ]);
    }

    /**
     * Generate user activity report
     */
    public function userActivityReport(Request $request)
    {
        $validated = $request->validate([
            'workspace_id' => 'nullable|exists:workspaces,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'user_id' => 'nullable|exists:users,id',
            'action' => 'nullable|string|max:120'
        ]);

        $query = AuditLog::with(['user', 'workspace'])
            ->when($request->workspace_id, function ($q, $workspaceId) {
                return $q->where('workspace_id', $workspaceId);
            })
            ->when($request->user_id, function ($q, $userId) {
                return $q->where('actor_user_id', $userId);
            })
            ->when($request->action, function ($q, $action) {
                return $q->where('action', 'like', "%{$action}%");
            })
            ->when($request->start_date, function ($q, $startDate) {
                return $q->whereDate('created_at', '>=', $startDate);
            })
            ->when($request->end_date, function ($q, $endDate) {
                return $q->whereDate('created_at', '<=', $endDate);
            })
            ->orderBy('created_at', 'desc');

        $logs = $query->paginate(50);
        $summary = $this->getUserActivitySummary($validated);

        if ($request->wantsJson() || $request->has('htmx')) {
            return view('reports.partials.user-activity-table', compact('logs', 'summary'));
        }

        return view('reports.user-activity', compact('logs', 'summary'));
    }

    /**
     * Generate storage usage report
     */
    public function storageUsageReport(Request $request)
    {
        $validated = $request->validate([
            'workspace_id' => 'nullable|exists:workspaces,id',
            'time_range' => 'nullable|in:7days,30days,90days,year_to_date,all_time'
        ]);

        $workspaceId = $request->workspace_id;
        $timeRange = $request->time_range ?? '30days';

        $usageData = Workspace::when($workspaceId, function ($q, $workspaceId) {
                return $q->where('id', $workspaceId);
            })
            ->with(['documents' => function ($query) use ($timeRange) {
                if ($timeRange !== 'all_time') {
                    $query->where('created_at', '>=', $this->getDateFromRange($timeRange));
                }
            }])
            ->get()
            ->map(function ($workspace) {
                return [
                    'workspace' => $workspace->name,
                    'total_quota' => $workspace->storage_quota,
                    'used_storage' => $workspace->storage_used,
                    'usage_percentage' => $workspace->storage_quota > 0 
                        ? round(($workspace->storage_used / $workspace->storage_quota) * 100, 2)
                        : 0,
                    'document_count' => $workspace->documents->count(),
                    'largest_document' => $workspace->documents->max('size_bytes') ?? 0
                ];
            });

        $totalUsage = $usageData->sum('used_storage');
        $totalQuota = $usageData->sum('total_quota');

        if ($request->wantsJson() || $request->has('htmx')) {
            return view('reports.partials.storage-usage-table', compact('usageData', 'totalUsage', 'totalQuota'));
        }

        return view('reports.storage-usage', compact('usageData', 'totalUsage', 'totalQuota'));
    }

    /**
     * Generate compliance status report
     */
    public function complianceStatusReport(Request $request)
    {
        $validated = $request->validate([
            'workspace_id' => 'nullable|exists:workspaces,id',
            'compliance_type' => 'nullable|in:retention,legal_hold,disposal,signatures'
        ]);

        $workspaceId = $request->workspace_id;
        $complianceType = $request->compliance_type;

        $reportData = [];

        // Retention Policies Compliance
        if (!$complianceType || $complianceType === 'retention') {
            $retentionData = RetentionPolicy::with(['workspace', 'documents'])
                ->when($workspaceId, function ($q, $workspaceId) {
                    return $q->where('workspace_id', $workspaceId);
                })
                ->get()
                ->map(function ($policy) {
                    $expiredCount = $policy->documents()
                        ->where('expires_at', '<=', now())
                        ->count();
                    
                    return [
                        'policy_name' => $policy->name,
                        'workspace' => $policy->workspace->name,
                        'total_documents' => $policy->documents->count(),
                        'expired_documents' => $expiredCount,
                        'compliance_status' => $expiredCount === 0 ? 'compliant' : 'non_compliant'
                    ];
                });

            $reportData['retention'] = $retentionData;
        }

        // Legal Holds Compliance
        if (!$complianceType || $complianceType === 'legal_hold') {
            $legalHoldData = LegalHold::with(['workspace', 'items'])
                ->when($workspaceId, function ($q, $workspaceId) {
                    return $q->where('workspace_id', $workspaceId);
                })
                ->where('is_active', true)
                ->get()
                ->map(function ($hold) {
                    return [
                        'case_name' => $hold->name,
                        'workspace' => $hold->workspace->name,
                        'items_count' => $hold->items->count(),
                        'status' => $hold->is_active ? 'active' : 'released'
                    ];
                });

            $reportData['legal_hold'] = $legalHoldData;
        }

        if ($request->wantsJson() || $request->has('htmx')) {
            return view('reports.partials.compliance-status-table', compact('reportData'));
        }

        return view('reports.compliance-status', compact('reportData'));
    }

    /**
     * Export report in various formats
     */
    public function exportReport(Request $request)
    {
        $validated = $request->validate([
            'report_type' => 'required|in:user_activity,storage_usage,compliance_status',
            'format' => 'required|in:pdf,csv,json',
            'parameters' => 'nullable|array'
        ]);

        $reportType = $request->report_type;
        $format = $request->format;
        $parameters = $request->parameters ?? [];

        $data = $this->generateExportData($reportType, $parameters);

        switch ($format) {
            case 'pdf':
                $pdf = Pdf::loadView("reports.exports.{$reportType}-pdf", $data);
                return $pdf->download("{$reportType}_report_" . now()->format('Y-m-d') . '.pdf');

            case 'csv':
                return $this->exportToCsv($data, $reportType);

            case 'json':
                return response()->json($data);
        }
    }

    /**
     * Schedule automated report generation
     */
    public function scheduleReport(Request $request)
    {
        $validated = $request->validate([
            'report_type' => 'required|in:user_activity,storage_usage,compliance_status',
            'frequency' => 'required|in:daily,weekly,monthly',
            'recipients' => 'required|array',
            'recipients.*' => 'email',
            'format' => 'required|in:pdf,csv',
            'parameters' => 'nullable|array'
        ]);

        // Create scheduled report entry
        $scheduledReport = ScheduledReport::create([
            'user_id' => auth()->id(),
            'report_type' => $request->report_type,
            'frequency' => $request->frequency,
            'recipients' => $request->recipients,
            'format' => $request->format,
            'parameters' => $request->parameters,
            'next_run_at' => $this->calculateNextRun($request->frequency),
            'is_active' => true
        ]);

        return response()->json([
            'message' => 'Report scheduled successfully',
            'data' => $scheduledReport
        ]);
    }

    /**
     * Get available report templates
     */
    public function getReportTemplates()
    {
        $templates = [
            'standard_user_activity' => [
                'name' => 'Standard User Activity',
                'description' => 'Daily user activity summary',
                'parameters' => ['time_range' => '7days']
            ],
            'monthly_storage_report' => [
                'name' => 'Monthly Storage Report',
                'description' => 'Monthly storage usage across all workspaces',
                'parameters' => ['time_range' => '30days']
            ],
            'compliance_audit' => [
                'name' => 'Compliance Audit',
                'description' => 'Full compliance status report',
                'parameters' => ['compliance_type' => null]
            ]
        ];

        return response()->json($templates);
    }

    /**
     * Helper methods
     */
    private function getUserActivitySummary($filters)
    {
        return AuditLog::when($filters['workspace_id'] ?? null, function ($q, $workspaceId) {
                return $q->where('workspace_id', $workspaceId);
            })
            ->when($filters['start_date'] ?? null, function ($q, $startDate) {
                return $q->whereDate('created_at', '>=', $startDate);
            })
            ->when($filters['end_date'] ?? null, function ($q, $endDate) {
                return $q->whereDate('created_at', '<=', $endDate);
            })
            ->select(
                DB::raw('COUNT(*) as total_actions'),
                DB::raw('COUNT(DISTINCT actor_user_id) as unique_users'),
                DB::raw('MAX(created_at) as last_action')
            )
            ->first();
    }

    private function getDateFromRange($range)
    {
        return match($range) {
            '7days' => now()->subDays(7),
            '30days' => now()->subDays(30),
            '90days' => now()->subDays(90),
            'year_to_date' => now()->startOfYear(),
            default => now()->subDays(30)
        };
    }

    private function generateExportData($reportType, $parameters)
    {
        // Implement data generation based on report type
        // This would call the appropriate report method with parameters
        return [];
    }

    private function exportToCsv($data, $reportType)
    {
        // Implement CSV export logic
        $filename = "{$reportType}_report_" . now()->format('Y-m-d') . '.csv';
        
        return response()->streamDownload(function () use ($data) {
            $output = fopen('php://output', 'w');
            // Add CSV headers and data
            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function calculateNextRun($frequency)
    {
        return match($frequency) {
            'daily' => now()->addDay(),
            'weekly' => now()->addWeek(),
            'monthly' => now()->addMonth(),
            default => now()->addDay()
        };
    }
}