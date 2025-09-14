<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Models\Acl;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ClientWorkspacesController extends Controller
{
    /**
     * Display a listing of client workspaces.
     */
    public function index()
    {
        // Get workspaces where the authenticated user has access
        $workspaces = Workspace::with(['owner', 'members.user'])
            ->whereHas('members', function ($query) {
                $query->where('user_id', auth()->id())
                    ->where('status', 'active');
            })
            ->where('is_archived', false)
            ->orderBy('name')
            ->get();

        return view('client-workspaces.index', compact('workspaces'));
    }

    /**
     * Display the specified client workspace.
     */
    public function show($clientWorkspaceId)
    {
        $workspace = Workspace::with([
            'owner',
            'members.user',
            'members.role',
            'folders' => function ($query) {
                $query->where('is_deleted', false)
                    ->orderBy('path');
            },
            'settings'
        ])->findOrFail($clientWorkspaceId);

        // Check if user has access to this workspace
        if (!Gate::allows('access-workspace', $workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        // Get workspace statistics
        $stats = [
            'total_documents' => $workspace->documents()->where('is_deleted', false)->count(),
            'total_members' => $workspace->members()->where('status', 'active')->count(),
            'storage_used' => $workspace->storage_used,
            'storage_quota' => $workspace->storage_quota,
            'storage_percentage' => $workspace->storage_quota > 0 
                ? round(($workspace->storage_used / $workspace->storage_quota) * 100, 2) 
                : 0
        ];

        // Get recent activity
        $recentActivity = DB::table('audit_logs')
            ->where('workspace_id', $workspace->id)
            ->where('actor_user_id', '!=', auth()->id())
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('client-workspaces.show', compact('workspace', 'stats', 'recentActivity'));
    }

    /**
     * Update access rules for the specified client workspace.
     */
    public function updateAccessRules(Request $request, $clientWorkspaceId)
    {
        $workspace = Workspace::findOrFail($clientWorkspaceId);

        // Check if user has permission to manage workspace access
        if (!Gate::allows('manage-workspace-access', $workspace)) {
            abort(403, 'You do not have permission to manage access rules for this workspace.');
        }

        $validated = $request->validate([
            'access_rules' => 'required|array',
            'access_rules.*.subject_type' => 'required|in:user,role',
            'access_rules.*.subject_id' => 'required|integer',
            'access_rules.*.resource_type' => 'required|in:workspace,folder,document',
            'access_rules.*.resource_id' => 'nullable|integer',
            'access_rules.*.permission' => 'required|string',
            'access_rules.*.effect' => 'required|in:allow,deny'
        ]);

        DB::transaction(function () use ($workspace, $validated) {
            // First, remove existing ACLs for this workspace
            Acl::where('workspace_id', $workspace->id)->delete();

            // Create new ACLs
            foreach ($validated['access_rules'] as $rule) {
                // Find permission ID
                $permission = Permission::where('key_name', $rule['permission'])->first();
                
                if ($permission) {
                    Acl::create([
                        'workspace_id' => $workspace->id,
                        'subject_type' => $rule['subject_type'],
                        'subject_id' => $rule['subject_id'],
                        'resource_type' => $rule['resource_type'],
                        'resource_id' => $rule['resource_id'] ?? null,
                        'permission_id' => $permission->id,
                        'effect' => $rule['effect'],
                        'created_by' => auth()->id()
                    ]);
                }
            }

            // Log the access rules update
            DB::table('audit_logs')->insert([
                'workspace_id' => $workspace->id,
                'actor_user_id' => auth()->id(),
                'action' => 'update_access_rules',
                'resource_type' => 'workspace',
                'resource_id' => $workspace->id,
                'ip_address' => inet_pton($request->ip()),
                'user_agent' => $request->userAgent(),
                'metadata' => json_encode([
                    'rules_count' => count($validated['access_rules']),
                    'updated_at' => now()->toISOString()
                ]),
                'created_at' => now()
            ]);
        });

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Access rules updated successfully.',
                'workspace_id' => $workspace->id
            ]);
        }

        return redirect()->route('client-workspaces.show', $workspace->id)
            ->with('success', 'Access rules updated successfully.');
    }
}