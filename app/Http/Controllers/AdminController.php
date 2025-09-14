<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Workspace;
use App\Models\Role;
use App\Models\Permission;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\RetentionPolicy;
use App\Models\LegalHold;
use App\Models\SystemSetting;
use Carbon\Carbon;

class AdminController extends Controller
{
    // Core Administration
    public function panel()
    {
        // Get system statistics
        $stats = [
            'total_users' => User::count(),
            'total_workspaces' => Workspace::count(),
            'active_workspaces' => Workspace::where('is_archived', false)->count(),
            'storage_used' => Workspace::sum('storage_used'),
            'storage_quota' => Workspace::sum('storage_quota'),
        ];

        // Recent activity
        $recentActivity = AuditLog::with(['user', 'workspace'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('admin.panel', compact('stats', 'recentActivity'));
    }

    public function getSettings()
    {
        $settings = SystemSetting::all()->pluck('v', 'k');
        return view('admin.settings.general', compact('settings'));
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'system_name' => 'required|string|max:255',
            'allow_registration' => 'boolean',
            'maintenance_mode' => 'boolean',
            'session_lifetime' => 'integer|min:15',
            'default_storage_quota' => 'integer|min:104857600', // 100MB min
        ]);

        foreach ($validated as $key => $value) {
            SystemSetting::updateOrCreate(
                ['k' => $key],
                ['v' => json_encode($value)]
            );
        }

        return back()->with('success', 'Settings updated successfully.');
    }

    public function uploadBranding(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:png,svg|max:2048',
            'favicon' => 'nullable|image|mimes:ico,png|max:1024',
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('branding', 'public');
            SystemSetting::updateOrCreate(
                ['k' => 'branding.logo'],
                ['v' => json_encode($path)]
            );
        }

        if ($request->hasFile('favicon')) {
            $path = $request->file('favicon')->store('branding', 'public');
            SystemSetting::updateOrCreate(
                ['k' => 'branding.favicon'],
                ['v' => json_encode($path)]
            );
        }

        return back()->with('success', 'Branding assets uploaded successfully.');
    }

    public function updateSecuritySettings(Request $request)
    {
        $validated = $request->validate([
            'password_min_length' => 'integer|min:8|max:32',
            'password_require_mixed_case' => 'boolean',
            'password_require_numbers' => 'boolean',
            'password_require_symbols' => 'boolean',
            '2fa_enforced' => 'boolean',
            'login_attempts_before_lockout' => 'integer|min:3|max:10',
            'lockout_duration_minutes' => 'integer|min:1|max:1440',
        ]);

        foreach ($validated as $key => $value) {
            SystemSetting::updateOrCreate(
                ['k' => "security.$key"],
                ['v' => json_encode($value)]
            );
        }

        return back()->with('success', 'Security settings updated successfully.');
    }

    public function impersonate(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        
        // Store original admin ID in session
        session()->put('impersonator', auth()->id());
        
        auth()->login($user);
        
        return redirect('/')->with('info', "Now impersonating {$user->email}");
    }

    // User & Workspace Management
    public function listUsers(Request $request)
    {
        $users = User::with(['company', 'workspaces'])
            ->when($request->search, function($query, $search) {
                $query->where('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return view('admin.users.index', compact('users'));
    }

    public function createUser(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:users',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'password' => 'required|min:8|confirmed',
            'company_id' => 'nullable|exists:companies,id',
            'time_zone' => 'required|timezone',
            'locale' => 'required|in:en,es,fr,de',
        ]);

        $user = User::create([
            'email' => $validated['email'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'password_hash' => Hash::make($validated['password']),
            'company_id' => $validated['company_id'],
            'time_zone' => $validated['time_zone'],
            'locale' => $validated['locale'],
        ]);

        return redirect()->route('admin.users')
            ->with('success', "User {$user->email} created successfully.");
    }

    public function getWorkspaceRequests()
    {
        $pendingRequests = DB::table('workspace_requests')
            ->where('status', 'pending')
            ->with(['user', 'workspace'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.workspaces.requests', compact('pendingRequests'));
    }

    public function approveWorkspaceRequest($requestId)
    {
        $request = DB::table('workspace_requests')->findOrFail($requestId);
        
        DB::transaction(function() use ($request) {
            // Create workspace
            $workspace = Workspace::create([
                'name' => $request->workspace_name,
                'description' => $request->description,
                'owner_user_id' => $request->user_id,
            ]);

            // Add user as owner
            $ownerRole = Role::where('key_name', 'workspace_owner')->first();
            DB::table('workspace_members')->insert([
                'workspace_id' => $workspace->id,
                'user_id' => $request->user_id,
                'role_id' => $ownerRole->id,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            // Update request status
            DB::table('workspace_requests')
                ->where('id', $requestId)
                ->update(['status' => 'approved', 'processed_at' => now()]);
        });

        return back()->with('success', 'Workspace request approved.');
    }

    public function rejectWorkspaceRequest($requestId)
    {
        DB::table('workspace_requests')
            ->where('id', $requestId)
            ->update(['status' => 'rejected', 'processed_at' => now()]);

        return back()->with('info', 'Workspace request rejected.');
    }

    public function indexWorkspaces(Request $request)
    {
        $workspaces = Workspace::with(['owner', 'members'])
            ->when($request->search, function($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.workspaces.index', compact('workspaces'));
    }

    public function archiveWorkspace($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $workspace->update(['is_archived' => true]);

        return back()->with('success', "Workspace {$workspace->name} archived.");
    }

    // Advanced Security & Compliance 
    public function getDataResidencySettings()
    {
        $settings = SystemSetting::where('k', 'like', 'data_residency.%')
            ->pluck('v', 'k');
            
        return view('admin.compliance.data-residency', compact('settings'));
    }

    public function updateDataResidencySettings(Request $request)
    {
        $validated = $request->validate([
            'default_region' => 'required|in:us,eu,asia',
            'allow_cross_region_transfer' => 'boolean',
            'data_encryption_at_rest' => 'boolean',
            'data_encryption_in_transit' => 'boolean',
        ]);

        foreach ($validated as $key => $value) {
            SystemSetting::updateOrCreate(
                ['k' => "data_residency.$key"],
                ['v' => json_encode($value)]
            );
        }

        return back()->with('success', 'Data residency settings updated.');
    }

    public function getComplianceReports(Request $request)
    {
        $reports = DB::table('compliance_reports')
            ->when($request->type, function($query, $type) {
                $query->where('report_type', $type);
            })
            ->orderBy('generated_at', 'desc')
            ->paginate(15);

        return view('admin.compliance.reports', compact('reports'));
    }

    public function exportComplianceData(Request $request)
    {
        $validated = $request->validate([
            'data_types' => 'required|array',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'format' => 'required|in:csv,json,xml',
        ]);

        // Generate export job
        $exportId = Str::uuid();
        DB::table('compliance_exports')->insert([
            'export_id' => $exportId,
            'requested_by' => auth()->id(),
            'data_types' => json_encode($validated['data_types']),
            'date_range' => json_encode([
                'start' => $validated['start_date'],
                'end' => $validated['end_date'],
            ]),
            'format' => $validated['format'],
            'status' => 'pending',
            'created_at' => now(),
        ]);

        return back()->with('success', 
            "Compliance export queued. ID: {$exportId}");
    }

    public function configureInformationBarriers(Request $request)
    {
        $validated = $request->validate([
            'policies' => 'required|array',
            'policies.*.name' => 'required|string',
            'policies.*.conditions' => 'required|array',
        ]);

        foreach ($validated['policies'] as $policy) {
            DB::table('information_barrier_policies')->updateOrInsert(
                ['name' => $policy['name']],
                ['conditions' => json_encode($policy['conditions'])]
            );
        }

        return back()->with('success', 'Information barriers configured.');
    }

    public function getAuditTrail(Request $request)
    {
        $logs = AuditLog::with(['user', 'workspace'])
            ->when($request->action, function($query, $action) {
                $query->where('action', $action);
            })
            ->when($request->user_id, function($query, $userId) {
                $query->where('actor_user_id', $userId);
            })
            ->when($request->workspace_id, function($query, $workspaceId) {
                $query->where('workspace_id', $workspaceId);
            })
            ->when($request->date_from, function($query, $date) {
                $query->where('created_at', '>=', $date);
            })
            ->when($request->date_to, function($query, $date) {
                $query->where('created_at', '<=', $date);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return view('admin.audit.index', compact('logs'));
    }

    public function exportAuditLogs(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'format' => 'required|in:csv,json',
        ]);

        $logs = AuditLog::with(['user', 'workspace'])
            ->whereBetween('created_at', [
                $validated['start_date'], 
                $validated['end_date']
            ])
            ->get();

        $filename = "audit-logs-".now()->format('Y-m-d').".{$validated['format']}";
        
        if ($validated['format'] === 'csv') {
            return $this->exportAuditToCsv($logs, $filename);
        }

        return response()->json($logs)->setEncodingOptions(
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    public function configureRetentionPolicies(Request $request)
    {
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'name' => 'required|string|max:160',
                'description' => 'nullable|string',
                'keep_rule' => 'required|array',
                'action' => 'required|in:delete,archive,review',
                'is_active' => 'boolean',
            ]);

            RetentionPolicy::create($validated);

            return back()->with('success', 'Retention policy created.');
        }

        $policies = RetentionPolicy::with(['workspace', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.compliance.retention-policies', compact('policies'));
    }

    public function manageLegalHolds(Request $request)
    {
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'case_number' => 'nullable|string|max:100',
                'workspace_id' => 'required|exists:workspaces,id',
                'resource_ids' => 'required|array',
                'resource_type' => 'required|in:folder,document',
            ]);

            $legalHold = LegalHold::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'case_number' => $validated['case_number'],
                'workspace_id' => $validated['workspace_id'],
                'issued_by' => auth()->id(),
            ]);

            foreach ($validated['resource_ids'] as $resourceId) {
                DB::table('legal_hold_items')->insert([
                    'legal_hold_id' => $legalHold->id,
                    'resource_type' => $validated['resource_type'],
                    'resource_id' => $resourceId,
                    'placed_by' => auth()->id(),
                    'placed_at' => now(),
                ]);
            }

            return back()->with('success', 'Legal hold created.');
        }

        $legalHolds = LegalHold::with(['workspace', 'issuedBy'])
            ->orderBy('issued_at', 'desc')
            ->get();

        return view('admin.compliance.legal-holds', compact('legalHolds'));
    }

    public function getSystemHealth()
    {
        $health = [
            'database' => $this->checkDatabaseHealth(),
            'storage' => $this->checkStorageHealth(),
            'queue' => $this->checkQueueHealth(),
            'cache' => $this->checkCacheHealth(),
            'last_cron_run' => SystemSetting::where('k', 'system.last_cron_run')->first()?->v,
        ];

        return view('admin.system.health', compact('health'));
    }

    public function manageStorageQuotas(Request $request)
    {
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'workspace_id' => 'required|exists:workspaces,id',
                'storage_quota' => 'required|integer|min:1073741824', // 1GB min
            ]);

            Workspace::where('id', $validated['workspace_id'])
                ->update(['storage_quota' => $validated['storage_quota']]);

            return back()->with('success', 'Storage quota updated.');
        }

        $workspaces = Workspace::with(['owner'])
            ->select('id', 'name', 'storage_used', 'storage_quota', 'owner_user_id')
            ->orderBy('storage_used', 'desc')
            ->paginate(20);

        return view('admin.storage.quotas', compact('workspaces'));
    }

    // API & Integration Management 
    public function manageApiKeys()
    {
        $apiKeys = ApiKey::with(['user'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.api.keys', compact('apiKeys'));
    }

    public function createApiKey(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'user_id' => 'required|exists:users,id',
            'expires_at' => 'nullable|date|after:today',
        ]);

        $token = Str::random(64);
        $apiKey = ApiKey::create([
            'name' => $validated['name'],
            'user_id' => $validated['user_id'],
            'key_hash' => Hash::make($token),
            'expires_at' => $validated['expires_at'],
        ]);

        return response()->json([
            'api_key' => $apiKey,
            'token' => $token, // Only show this once!
        ]);
    }

    public function revokeApiKey($keyId)
    {
        $apiKey = ApiKey::findOrFail($keyId);
        $apiKey->delete();

        return back()->with('success', 'API key revoked.');
    }

    public function getApiUsageMetrics(Request $request)
    {
        $metrics = DB::table('api_usage_logs')
            ->select(
                'user_id',
                DB::raw('COUNT(*) as total_requests'),
                DB::raw('SUM(duration_ms) as total_duration'),
                DB::raw('AVG(duration_ms) as avg_duration'),
                DB::raw('MAX(created_at) as last_request')
            )
            ->when($request->date_from, function($query, $date) {
                $query->where('created_at', '>=', $date);
            })
            ->when($request->date_to, function($query, $date) {
                $query->where('created_at', '<=', $date);
            })
            ->groupBy('user_id')
            ->orderBy('total_requests', 'desc')
            ->paginate(20);

        return view('admin.api.metrics', compact('metrics'));
    }

    // User & Permission Governance 
    public function manageUserGroups()
    {
        $groups = DB::table('user_groups')
            ->with(['createdBy', 'members'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.users.groups', compact('groups'));
    }

    public function createUserGroup(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $groupId = DB::table('user_groups')->insertGetId([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);

        foreach ($validated['user_ids'] as $userId) {
            DB::table('user_group_members')->insert([
                'group_id' => $groupId,
                'user_id' => $userId,
                'created_at' => now(),
            ]);
        }

        return back()->with('success', 'User group created.');
    }

    public function assignGroupPermissions(Request $request)
    {
        $validated = $request->validate([
            'group_id' => 'required|exists:user_groups,id',
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,key_name',
        ]);

        DB::table('user_group_permissions')
            ->where('group_id', $validated['group_id'])
            ->delete();

        foreach ($validated['permissions'] as $permissionKey) {
            $permission = Permission::where('key_name', $permissionKey)->first();
            if ($permission) {
                DB::table('user_group_permissions')->insert([
                    'group_id' => $validated['group_id'],
                    'permission_id' => $permission->id,
                    'created_at' => now(),
                ]);
            }
        }

        return back()->with('success', 'Group permissions updated.');
    }

    public function getPermissionTemplates()
    {
        $templates = DB::table('permission_templates')
            ->orderBy('name')
            ->get();

        return view('admin.permissions.templates', compact('templates'));
    }

    public function applyPermissionTemplate($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $templateId = request('template_id');

        // Logic to apply template permissions to workspace
        // This would typically copy role-permission mappings

        return back()->with('success', 
            "Permission template applied to {$workspace->name}.");
    }

    // Customization & Branding 
    public function uploadCustomLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:png,svg|max:2048',
            'type' => 'required|in:header,login,email',
        ]);

        $path = $request->file('logo')->store("branding/{$request->type}", 'public');
        
        SystemSetting::updateOrCreate(
            ['k' => "branding.{$request->type}_logo"],
            ['v' => json_encode($path)]
        );

        return back()->with('success', 
            ucfirst($request->type) . ' logo uploaded successfully.');
    }

    public function setColorScheme(Request $request)
    {
        $validated = $request->validate([
            'primary_color' => 'required|string|size:7',
            'secondary_color' => 'required|string|size:7',
            'accent_color' => 'required|string|size:7',
            'dark_mode' => 'boolean',
        ]);

        SystemSetting::updateOrCreate(
            ['k' => 'branding.color_scheme'],
            ['v' => json_encode($validated)]
        );

        return back()->with('success', 'Color scheme updated.');
    }

    public function customizeEmailTemplates(Request $request)
    {
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'template_key' => 'required|exists:email_templates,template_key',
                'subject' => 'required|string|max:255',
                'body_html' => 'required|string',
                'body_text' => 'required|string',
            ]);

            DB::table('email_templates')
                ->where('template_key', $validated['template_key'])
                ->update([
                    'subject' => $validated['subject'],
                    'body_html' => $validated['body_html'],
                    'body_text' => $validated['body_text'],
                    'updated_at' => now(),
                ]);

            return back()->with('success', 'Email template updated.');
        }

        $templates = DB::table('email_templates')->get();
        return view('admin.branding.email-templates', compact('templates'));
    }

    public function setCustomDomain(Request $request)
    {
        $validated = $request->validate([
            'custom_domain' => 'required|string|max:255',
            'ssl_certificate' => 'nullable|string',
            'ssl_private_key' => 'nullable|string',
        ]);

        SystemSetting::updateOrCreate(
            ['k' => 'branding.custom_domain'],
            ['v' => json_encode($validated)]
        );

        return back()->with('success', 'Custom domain configured.');
    }

    public function getBrandingPreview()
    {
        $branding = SystemSetting::where('k', 'like', 'branding.%')
            ->pluck('v', 'k');
            
        return view('admin.branding.preview', compact('branding'));
    }

    // Helper methods
    private function exportAuditToCsv($logs, $filename)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($logs) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Timestamp', 'User', 'Workspace', 'Action', 
                'Resource Type', 'Resource ID', 'IP Address'
            ]);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->created_at,
                    $log->user ? $log->user->email : 'System',
                    $log->workspace ? $log->workspace->name : 'N/A',
                    $log->action,
                    $log->resource_type,
                    $log->resource_id,
                    $log->ip_address,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function checkDatabaseHealth()
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'healthy', 'message' => 'Connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    private function checkStorageHealth()
    {
        $free = disk_free_space(storage_path());
        $total = disk_total_space(storage_path());
        $percent = ($total - $free) / $total * 100;

        return [
            'status' => $percent > 90 ? 'warning' : 'healthy',
            'message' => "Storage usage: " . round($percent, 2) . "%",
            'free' => $free,
            'total' => $total,
        ];
    }

    private function checkQueueHealth()
    {
        $failed = DB::table('failed_jobs')->count();
        $pending = DB::table('jobs')->count();

        return [
            'status' => $failed > 0 ? 'warning' : 'healthy',
            'message' => "Pending: {$pending}, Failed: {$failed}",
            'pending' => $pending,
            'failed' => $failed,
        ];
    }

    private function checkCacheHealth()
    {
        try {
            Cache::put('healthcheck', 'ok', 10);
            return Cache::get('healthcheck') === 'ok' 
                ? ['status' => 'healthy', 'message' => 'Cache functioning']
                : ['status' => 'unhealthy', 'message' => 'Cache retrieval failed'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }
}