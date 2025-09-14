<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\Workspace;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TeamsController extends Controller
{
    // Core Actions
    
    /**
     * Display a listing of teams for a workspace
     */
    public function index($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $teams = Team::with(['members.user', 'members.role'])
                    ->where('workspace_id', $workspaceId)
                    ->get();
        
        return view('teams.index', compact('workspace', 'teams'));
    }

    /**
     * Update a team
     */
    public function update(Request $request, $teamId)
    {
        $team = Team::findOrFail($teamId);
        
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
        ]);
        
        $team->update($validated);
        
        if ($request->wantsJson() || $request->header('HX-Request')) {
            return response()->json([
                'success' => true,
                'message' => 'Team updated successfully',
                'team' => $team
            ]);
        }
        
        return redirect()->back()->with('success', 'Team updated successfully');
    }

    /**
     * Delete a team
     */
    public function delete($teamId)
    {
        $team = Team::findOrFail($teamId);
        $team->delete();
        
        if (request()->wantsJson() || request()->header('HX-Request')) {
            return response()->json([
                'success' => true,
                'message' => 'Team deleted successfully'
            ]);
        }
        
        return redirect()->back()->with('success', 'Team deleted successfully');
    }

    /**
     * Sync team permissions with roles
     */
    public function syncPermissions(Request $request, $teamId)
    {
        $team = Team::findOrFail($teamId);
        
        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,key_name'
        ]);
        
        // Get permission IDs from key names
        $permissionIds = Permission::whereIn('key_name', $validated['permissions'])
                                ->pluck('id')
                                ->toArray();
        
        // Create or update a custom role for this team
        $role = Role::firstOrCreate(
            [
                'workspace_id' => $team->workspace_id,
                'key_name' => 'team_' . $team->id
            ],
            [
                'label' => 'Team: ' . $team->name,
                'is_system_role' => false
            ]
        );
        
        // Sync permissions for the role
        $role->permissions()->sync($permissionIds);
        
        // Assign this role to all team members
        DB::table('team_members')
            ->where('team_id', $teamId)
            ->update(['role' => $role->key_name]);
        
        if ($request->wantsJson() || $request->header('HX-Request')) {
            return response()->json([
                'success' => true,
                'message' => 'Team permissions synced successfully',
                'role' => $role
            ]);
        }
        
        return redirect()->back()->with('success', 'Team permissions synced successfully');
    }

    /**
     * Add user to team
     */
    public function addUser(Request $request, $teamId)
    {
        $team = Team::findOrFail($teamId);
        
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'nullable|string|max:60'
        ]);
        
        // Check if user is already in the team
        $existingMember = DB::table('team_members')
                            ->where('team_id', $teamId)
                            ->where('user_id', $validated['user_id'])
                            ->first();
        
        if ($existingMember) {
            if ($request->wantsJson() || $request->header('HX-Request')) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is already in this team'
                ], 422);
            }
            
            return redirect()->back()->with('error', 'User is already in this team');
        }
        
        // Add user to team
        DB::table('team_members')->insert([
            'team_id' => $teamId,
            'user_id' => $validated['user_id'],
            'role' => $validated['role'] ?? 'member',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        if ($request->wantsJson() || $request->header('HX-Request')) {
            return response()->json([
                'success' => true,
                'message' => 'User added to team successfully'
            ]);
        }
        
        return redirect()->back()->with('success', 'User added to team successfully');
    }

    /**
     * Remove user from team
     */
    public function removeUser(Request $request, $teamId, $userId)
    {
        $team = Team::findOrFail($teamId);
        
        DB::table('team_members')
            ->where('team_id', $teamId)
            ->where('user_id', $userId)
            ->delete();
        
        if ($request->wantsJson() || $request->header('HX-Request')) {
            return response()->json([
                'success' => true,
                'message' => 'User removed from team successfully'
            ]);
        }
        
        return redirect()->back()->with('success', 'User removed from team successfully');
    }

    // Web & HTMX Fragments
    
    /**
     * HTMX: Team details fragment
     */
    public function team($workspaceId, $teamId)
    {
        $team = Team::with(['members.user', 'members.role'])
                   ->where('workspace_id', $workspaceId)
                   ->findOrFail($teamId);
        
        return view('teams.partials.team-details', compact('team'));
    }

    /**
     * HTMX: Team actions fragment (edit/delete buttons)
     */
    public function actions($workspaceId, $teamId)
    {
        $team = Team::where('workspace_id', $workspaceId)
                   ->findOrFail($teamId);
        
        return view('teams.partials.team-actions', compact('team'));
    }

    /**
     * HTMX: Teams table fragment
     */
    public function table($workspaceId)
    {
        $teams = Team::withCount('members')
                    ->where('workspace_id', $workspaceId)
                    ->get();
        
        return view('teams.partials.teams-table', compact('teams'));
    }

    /**
     * HTMX: Invite form fragment
     */
    public function inviteForm($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $users = User::whereDoesntHave('teams', function($query) use ($workspaceId) {
                    $query->where('workspace_id', $workspaceId);
                 })
                 ->get();
        
        return view('teams.partials.invite-form', compact('workspace', 'users'));
    }

    /**
     * Process team invitation
     */
    public function invite(Request $request, $workspaceId)
    {
        $validated = $request->validate([
            'team_id' => 'required|exists:teams,id',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'role' => 'nullable|string|max:60'
        ]);
        
        $team = Team::where('workspace_id', $workspaceId)
                   ->findOrFail($validated['team_id']);
        
        $insertData = [];
        $now = now();
        
        foreach ($validated['user_ids'] as $userId) {
            // Check if user is already in the team
            $existing = DB::table('team_members')
                         ->where('team_id', $team->id)
                         ->where('user_id', $userId)
                         ->exists();
            
            if (!$existing) {
                $insertData[] = [
                    'team_id' => $team->id,
                    'user_id' => $userId,
                    'role' => $validated['role'] ?? 'member',
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }
        }
        
        if (!empty($insertData)) {
            DB::table('team_members')->insert($insertData);
        }
        
        if ($request->header('HX-Request')) {
            return response()->view('teams.partials.invite-success', [
                'count' => count($insertData),
                'team' => $team
            ])->withHeaders(['HX-Trigger' => 'teamUpdated']);
        }
        
        return redirect()->back()->with('success', count($insertData) . ' users invited to team');
    }
}