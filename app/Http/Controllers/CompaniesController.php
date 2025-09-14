<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Models\Document;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CompaniesController extends Controller
{
    /**
     * Get public files shared by a company
     */
    public function getPublicFiles(Request $request, $companyId)
    {
        $company = Company::findOrFail($companyId);
        
        // Verify the authenticated user has access to this company's files
        if (!auth()->user()->companies()->where('companies.id', $companyId)->exists()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $files = Document::whereHas('workspace', function($query) use ($companyId) {
                $query->whereHas('members', function($q) use ($companyId) {
                    $q->whereHas('user', function($userQuery) use ($companyId) {
                        $userQuery->where('company_id', $companyId);
                    });
                });
            })
            ->where('confidentiality', 'public')
            ->where('is_deleted', 0)
            ->with(['workspace', 'uploader'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'company' => $company,
            'files' => $files
        ]);
    }

    /**
     * List all users belonging to a company
     */
    public function listUsers(Request $request, $companyId)
    {
        $company = Company::findOrFail($companyId);
        
        // Authorization check - only company admins or system admins can list users
        if (!auth()->user()->can('company.manage', $company)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $users = User::where('company_id', $companyId)
            ->where('is_active', 1)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'created_at', 'last_login_at']);

        return response()->json([
            'company' => $company,
            'users' => $users
        ]);
    }

    /**
     * Add a user to a company
     */
    public function addUser(Request $request, $companyId)
    {
        $company = Company::findOrFail($companyId);
        
        // Authorization check
        if (!auth()->user()->can('company.manage', $company)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'send_welcome_email' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $user = new User();
            $user->company_id = $companyId;
            $user->first_name = $request->first_name;
            $user->last_name = $request->last_name;
            $user->email = $request->email;
            $user->password_hash = Hash::make($request->password);
            $user->is_active = true;
            $user->save();

            // Add user to default workspace if company has one
            $defaultWorkspace = Workspace::where('company_id', $companyId)
                ->where('is_default', true)
                ->first();

            if ($defaultWorkspace) {
                $memberRole = Role::where('key_name', 'workspace_member')
                    ->where('workspace_id', $defaultWorkspace->id)
                    ->first();

                if ($memberRole) {
                    $workspaceMember = new WorkspaceMember();
                    $workspaceMember->workspace_id = $defaultWorkspace->id;
                    $workspaceMember->user_id = $user->id;
                    $workspaceMember->role_id = $memberRole->id;
                    $workspaceMember->status = 'active';
                    $workspaceMember->save();
                }
            }

            // Send welcome email if requested
            if ($request->boolean('send_welcome_email')) {
                // Queue welcome email
                // Mail::to($user->email)->queue(new WelcomeEmail($user, $request->password));
            }

            DB::commit();

            return response()->json([
                'message' => 'User added successfully',
                'user' => $user
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to add user: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove a user from a company
     */
    public function removeUser(Request $request, $companyId, $userId)
    {
        $company = Company::findOrFail($companyId);
        $user = User::where('company_id', $companyId)->findOrFail($userId);
        
        // Authorization check - cannot remove yourself
        if (auth()->id() === $user->id) {
            return response()->json(['error' => 'Cannot remove yourself'], 422);
        }

        if (!auth()->user()->can('company.manage', $company)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            DB::beginTransaction();

            // Remove from all company workspaces
            WorkspaceMember::where('user_id', $user->id)
                ->whereIn('workspace_id', function($query) use ($companyId) {
                    $query->select('id')
                        ->from('workspaces')
                        ->where('company_id', $companyId);
                })
                ->delete();

            // Remove company association
            $user->company_id = null;
            $user->save();

            DB::commit();

            return response()->json([
                'message' => 'User removed from company successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to remove user: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Deactivate a user in a company
     */
    public function deactivateUser(Request $request, $companyId, $userId)
    {
        $company = Company::findOrFail($companyId);
        $user = User::where('company_id', $companyId)->findOrFail($userId);
        
        // Authorization check - cannot deactivate yourself
        if (auth()->id() === $user->id) {
            return response()->json(['error' => 'Cannot deactivate yourself'], 422);
        }

        if (!auth()->user()->can('company.manage', $company)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            DB::beginTransaction();

            // Deactivate user
            $user->is_active = false;
            $user->deactivated_at = now();
            $user->deactivated_by = auth()->id();
            $user->save();

            // Revoke all active sessions
            DB::table('user_sessions')
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            // Revoke API keys
            DB::table('api_keys')
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            DB::commit();

            return response()->json([
                'message' => 'User deactivated successfully',
                'user' => $user
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to deactivate user: ' . $e->getMessage()], 500);
        }
    }
}