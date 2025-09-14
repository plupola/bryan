<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\Role;
use App\Models\WorkspaceSetting;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WorkspacesController extends Controller
{
    // Core CRUD
    public function index()
    {
        $user = auth()->user();
        $workspaces = $user->workspaces()->where('is_archived', false)->get();
        
        return view('workspaces.index', compact('workspaces'));
    }

    public function create()
    {
        return view('workspaces.create');
    }

    public function createForm()
    {
        return view('workspaces.partials.create-form');
    }

    public function show($workspaceId)
    {
        $workspace = Workspace::with(['members.user', 'folders'])->findOrFail($workspaceId);
        $this->authorize('view', $workspace);
        
        return view('workspaces.show', compact('workspace'));
    }

    public function update($workspaceId, Request $request)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('update', $workspace);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        
        $workspace->update($validated);
        
        // Log activity
        AuditLog::create([
            'workspace_id' => $workspace->id,
            'actor_user_id' => auth()->id(),
            'action' => 'workspace.updated',
            'resource_type' => 'workspace',
            'resource_id' => $workspace->id,
            'metadata' => json_encode(['changes' => $validated])
        ]);
        
        if ($request->header('HX-Request')) {
            return response()->view('workspaces.partials.workspace-header', compact('workspace'))
                ->withHeaders(['HX-Trigger' => 'workspaceUpdated']);
        }
        
        return redirect()->route('workspaces.show', $workspace->id)
            ->with('success', 'Workspace updated successfully.');
    }

    public function delete($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('delete', $workspace);
        
        DB::transaction(function () use ($workspace) {
            // Soft delete or archive instead of permanent deletion
            $workspace->update(['is_archived' => true]);
            
            AuditLog::create([
                'workspace_id' => $workspace->id,
                'actor_user_id' => auth()->id(),
                'action' => 'workspace.archived',
                'resource_type' => 'workspace',
                'resource_id' => $workspace->id,
            ]);
        });
        
        return redirect()->route('workspaces.index')
            ->with('success', 'Workspace archived successfully.');
    }

    // User Collaboration
    public function inviteUser($workspaceId, Request $request)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('manageMembers', $workspace);
        
        $validated = $request->validate([
            'emails' => 'required|string',
            'role_id' => 'required|exists:roles,id',
        ]);
        
        $emails = array_map('trim', explode(',', $validated['emails']));
        $role = Role::findOrFail($validated['role_id']);
        $inviter = auth()->user();
        $results = [];
        
        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $results[$email] = 'Invalid email';
                continue;
            }
            
            $user = User::firstOrCreate(['email' => $email], [
                'first_name' => explode('@', $email)[0],
                'password' => bcrypt(Str::random(16)),
                'is_active' => false,
            ]);
            
            // Check if user is already a member
            if ($workspace->members()->where('user_id', $user->id)->exists()) {
                $results[$email] = 'Already a member';
                continue;
            }
            
            WorkspaceMember::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role_id' => $role->id,
                'status' => 'invited'
            ]);
            
            // TODO: Send invitation email
            $results[$email] = 'Invited';
        }
        
        AuditLog::create([
            'workspace_id' => $workspace->id,
            'actor_user_id' => auth()->id(),
            'action' => 'workspace.invited_users',
            'resource_type' => 'workspace',
            'resource_id' => $workspace->id,
            'metadata' => json_encode(['results' => $results])
        ]);
        
        if ($request->header('HX-Request')) {
            return response()->view('workspaces.partials.member-list', [
                'members' => $workspace->members()->with('user', 'role')->get()
            ])->withHeaders(['HX-Trigger' => 'membersUpdated']);
        }
        
        return redirect()->route('workspaces.settings', $workspace->id)
            ->with('success', 'Users invited successfully.')
            ->with('invitationResults', $results);
    }

    public function archived()
    {
        $user = auth()->user();
        $archivedWorkspaces = $user->workspaces()->where('is_archived', true)->get();
        
        return view('workspaces.archived', compact('archivedWorkspaces'));
    }

    // Settings & Configuration
    public function getSettings($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('update', $workspace);
        
        $settings = $workspace->settings()->pluck('v', 'k');
        
        return response()->json($settings);
    }

    public function updateSettings($workspaceId, Request $request)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('update', $workspace);
        
        $settings = $request->except('_token');
        
        foreach ($settings as $key => $value) {
            WorkspaceSetting::updateOrCreate(
                ['workspace_id' => $workspace->id, 'k' => $key],
                ['v' => json_encode($value)]
            );
        }
        
        AuditLog::create([
            'workspace_id' => $workspace->id,
            'actor_user_id' => auth()->id(),
            'action' => 'workspace.settings_updated',
            'resource_type' => 'workspace',
            'resource_id' => $workspace->id,
        ]);
        
        return response()->json(['success' => true]);
    }

    public function settings($workspaceId)
    {
        $workspace = Workspace::with(['members.user', 'settings'])->findOrFail($workspaceId);
        $this->authorize('update', $workspace);
        
        $roles = Role::where('workspace_id', $workspaceId)
            ->orWhereNull('workspace_id')
            ->get();
            
        $settings = $workspace->settings()->pluck('v', 'k')->map(function ($item) {
            return json_decode($item, true);
        });
        
        return view('workspaces.settings', compact('workspace', 'roles', 'settings'));
    }

    // Stats & Overview
    public function getStats($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('view', $workspace);
        
        $stats = [
            'documents_count' => $workspace->documents()->count(),
            'members_count' => $workspace->members()->count(),
            'storage_used' => $workspace->storage_used,
            'storage_quota' => $workspace->storage_quota,
            'storage_percentage' => $workspace->storage_quota > 0 
                ? round(($workspace->storage_used / $workspace->storage_quota) * 100, 2) 
                : 0,
        ];
        
        return response()->json($stats);
    }

    public function overview($workspaceId)
    {
        $workspace = Workspace::with(['recentActivity'])->findOrFail($workspaceId);
        $this->authorize('view', $workspace);
        
        $stats = $this->getStats($workspaceId)->getData(true);
        
        return view('workspaces.overview', compact('workspace', 'stats'));
    }

    public function activity($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('view', $workspace);
        
        $activities = AuditLog::with('actor')
            ->where('workspace_id', $workspaceId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        return view('workspaces.activity', compact('workspace', 'activities'));
    }

    // Client & External Collaboration
    public function createClientWorkspace(Request $request)
    {
        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email',
            'workspace_name' => 'required|string|max:255',
        ]);
        
        $user = auth()->user();
        
        DB::transaction(function () use ($validated, $user) {
            // Create client user
            $clientUser = User::firstOrCreate(
                ['email' => $validated['client_email']],
                [
                    'first_name' => $validated['client_name'],
                    'password' => bcrypt(Str::random(16)),
                    'is_active' => true,
                ]
            );
            
            // Create workspace
            $workspace = Workspace::create([
                'name' => $validated['workspace_name'],
                'description' => "Client workspace for {$validated['client_name']}",
                'owner_user_id' => $user->id,
            ]);
            
            // Add client as member with limited permissions
            $clientRole = Role::firstOrCreate(
                ['workspace_id' => $workspace->id, 'key_name' => 'client'],
                [
                    'label' => 'Client',
                    'is_system_role' => false,
                ]
            );
            
            WorkspaceMember::create([
                'workspace_id' => $workspace->id,
                'user_id' => $clientUser->id,
                'role_id' => $clientRole->id,
                'status' => 'active'
            ]);
            
            // Add creator as admin
            $adminRole = Role::where('key_name', 'workspace_owner')
                ->whereNull('workspace_id')
                ->first();
                
            WorkspaceMember::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role_id' => $adminRole->id,
                'status' => 'active'
            ]);
            
            // Set external collaboration settings
            WorkspaceSetting::create([
                'workspace_id' => $workspace->id,
                'k' => 'external_collaboration.enabled',
                'v' => json_encode(true)
            ]);
            
            WorkspaceSetting::create([
                'workspace_id' => $workspace->id,
                'k' => 'external_collaboration.allow_guest_uploads',
                'v' => json_encode(true)
            ]);
            
            AuditLog::create([
                'workspace_id' => $workspace->id,
                'actor_user_id' => $user->id,
                'action' => 'workspace.created_client',
                'resource_type' => 'workspace',
                'resource_id' => $workspace->id,
                'metadata' => json_encode(['client_email' => $validated['client_email']])
            ]);
        });
        
        return redirect()->route('workspaces.index')
            ->with('success', 'Client workspace created successfully.');
    }

    public function manageClients($workspaceId)
    {
        $workspace = Workspace::with(['members.user'])->findOrFail($workspaceId);
        $this->authorize('manageMembers', $workspace);
        
        $clientMembers = $workspace->members()
            ->whereHas('role', function ($query) {
                $query->where('key_name', 'client');
            })
            ->with('user')
            ->get();
            
        return view('workspaces.clients', compact('workspace', 'clientMembers'));
    }

    public function getExternalCollaborationSettings($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('update', $workspace);
        
        $settings = $workspace->settings()
            ->where('k', 'like', 'external_collaboration.%')
            ->pluck('v', 'k')
            ->map(function ($value) {
                return json_decode($value, true);
            });
            
        return response()->json($settings);
    }

    public function updateExternalCollaborationSettings($workspaceId, Request $request)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('update', $workspace);
        
        $settings = $request->validate([
            'external_collaboration.enabled' => 'boolean',
            'external_collaboration.allow_guest_uploads' => 'boolean',
            'external_collaboration.guest_expiry_days' => 'integer|min:1|max:365',
            'external_collaboration.require_approval' => 'boolean',
        ]);
        
        foreach ($settings as $key => $value) {
            WorkspaceSetting::updateOrCreate(
                ['workspace_id' => $workspace->id, 'k' => $key],
                ['v' => json_encode($value)]
            );
        }
        
        AuditLog::create([
            'workspace_id' => $workspace->id,
            'actor_user_id' => auth()->id(),
            'action' => 'workspace.external_settings_updated',
            'resource_type' => 'workspace',
            'resource_id' => $workspace->id,
            'metadata' => json_encode($settings)
        ]);
        
        return response()->json(['success' => true]);
    }

    // HTMX Fragments
    public function sidebar($workspaceId)
    {
        $workspace = Workspace::with(['folders'])->findOrFail($workspaceId);
        $this->authorize('view', $workspace);
        
        return view('workspaces.partials.sidebar', compact('workspace'));
    }

    public function tab($workspaceId, $tab)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('view', $workspace);
        
        $view = "workspaces.tabs.{$tab}";
        
        if (!view()->exists($view)) {
            abort(404, "Tab view not found: {$view}");
        }
        
        $data = [];
        
        // Load tab-specific data
        switch ($tab) {
            case 'overview':
                $data['stats'] = $this->getStats($workspaceId)->getData(true);
                break;
            case 'activity':
                $data['activities'] = AuditLog::with('actor')
                    ->where('workspace_id', $workspaceId)
                    ->orderBy('created_at', 'desc')
                    ->paginate(10);
                break;
            case 'team':
                $data['members'] = $workspace->members()->with('user', 'role')->get();
                $data['roles'] = Role::where('workspace_id', $workspaceId)
                    ->orWhereNull('workspace_id')
                    ->get();
                break;
            case 'settings':
                $data['settings'] = $workspace->settings()->pluck('v', 'k')->map(function ($item) {
                    return json_decode($item, true);
                });
                break;
        }
        
        return view($view, array_merge(['workspace' => $workspace], $data));
    }

    public function team($workspaceId)
    {
        $workspace = Workspace::with(['members.user', 'members.role'])->findOrFail($workspaceId);
        $this->authorize('view', $workspace);
        
        $roles = Role::where('workspace_id', $workspaceId)
            ->orWhereNull('workspace_id')
            ->get();
            
        return view('workspaces.partials.team-tab', compact('workspace', 'roles'));
    }
}