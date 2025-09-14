<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Models\Workflow;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowStep;
use App\Models\WorkflowInstance;
use App\Models\WorkflowActivity;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WorkflowsController extends Controller
{
    /**
     * Display a listing of workflows for a workspace.
     */
    public function index($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('view', $workspace);
        
        $workflows = Workflow::with(['creator', 'steps', 'instances'])
            ->where('workspace_id', $workspaceId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        return view('workflows.index', compact('workflows', 'workspace'));
    }

    /**
     * Show the form for creating a new workflow.
     */
    public function create()
    {
        return view('workflows.create');
    }

    /**
     * Display the specified workflow.
     */
    public function show($workflowId)
    {
        $workflow = Workflow::with([
            'steps.assignedRole',
            'steps.assignedUser',
            'instances.document',
            'instances.currentStep',
            'creator'
        ])->findOrFail($workflowId);
        
        $this->authorize('view', $workflow->workspace);
        
        // Get recent activity
        $activities = WorkflowActivity::with(['user', 'instance.document'])
            ->where('workflow_id', $workflowId)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
            
        return view('workflows.show', compact('workflow', 'activities'));
    }

    /**
     * Update the specified workflow.
     */
    public function update($workflowId, Request $request)
    {
        $workflow = Workflow::findOrFail($workflowId);
        $this->authorize('update', $workflow);
        
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'steps' => 'sometimes|array',
            'steps.*.name' => 'required|string|max:255',
            'steps.*.type' => 'required|in:approval,review,notification',
            'steps.*.assigned_to_type' => 'required|in:user,role,specific',
            'steps.*.assigned_to_id' => 'nullable|exists:users,id',
            'steps.*.assigned_role_id' => 'nullable|exists:roles,id',
            'steps.*.time_limit_days' => 'nullable|integer|min:1',
            'steps.*.notify_assignee' => 'sometimes|boolean',
            'steps.*.is_required' => 'sometimes|boolean'
        ]);
        
        DB::transaction(function () use ($workflow, $validated) {
            $workflow->update([
                'name' => $validated['name'] ?? $workflow->name,
                'description' => $validated['description'] ?? $workflow->description,
                'is_active' => $validated['is_active'] ?? $workflow->is_active
            ]);
            
            // Update steps if provided
            if (isset($validated['steps'])) {
                // Delete existing steps
                WorkflowStep::where('workflow_id', $workflow->id)->delete();
                
                // Create new steps
                foreach ($validated['steps'] as $index => $stepData) {
                    WorkflowStep::create([
                        'workflow_id' => $workflow->id,
                        'name' => $stepData['name'],
                        'type' => $stepData['type'],
                        'step_order' => $index + 1,
                        'assigned_to_type' => $stepData['assigned_to_type'],
                        'assigned_to_id' => $stepData['assigned_to_id'] ?? null,
                        'assigned_role_id' => $stepData['assigned_role_id'] ?? null,
                        'time_limit_days' => $stepData['time_limit_days'] ?? null,
                        'notify_assignee' => $stepData['notify_assignee'] ?? true,
                        'is_required' => $stepData['is_required'] ?? true,
                        'created_by' => Auth::id()
                    ]);
                }
            }
        });
        
        // Log workflow update
        DB::table('audit_logs')->insert([
            'workspace_id' => $workflow->workspace_id,
            'actor_user_id' => Auth::id(),
            'action' => 'workflow_updated',
            'resource_type' => 'workflow',
            'resource_id' => $workflow->id,
            'metadata' => json_encode(['name' => $workflow->name]),
            'created_at' => now()
        ]);
        
        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json($workflow->load('steps'));
        }
        
        return redirect()->route('workflows.show', $workflowId)
            ->with('success', 'Workflow updated successfully');
    }

    /**
     * Delete the specified workflow.
     */
    public function delete($workflowId)
    {
        $workflow = Workflow::findOrFail($workflowId);
        $this->authorize('delete', $workflow);
        
        // Check if workflow has active instances
        $activeInstances = WorkflowInstance::where('workflow_id', $workflowId)
            ->where('status', '!=', 'completed')
            ->exists();
            
        if ($activeInstances) {
            return response()->json([
                'error' => 'Cannot delete workflow with active instances'
            ], 422);
        }
        
        DB::transaction(function () use ($workflow) {
            // Delete steps
            WorkflowStep::where('workflow_id', $workflow->id)->delete();
            
            // Delete activities
            WorkflowActivity::where('workflow_id', $workflow->id)->delete();
            
            // Delete the workflow
            $workflow->delete();
        });
        
        // Log workflow deletion
        DB::table('audit_logs')->insert([
            'workspace_id' => $workflow->workspace_id,
            'actor_user_id' => Auth::id(),
            'action' => 'workflow_deleted',
            'resource_type' => 'workflow',
            'resource_id' => $workflow->id,
            'metadata' => json_encode(['name' => $workflow->name]),
            'created_at' => now()
        ]);
        
        if (request()->wantsJson() || request()->is('api/*')) {
            return response()->json(['message' => 'Workflow deleted successfully']);
        }
        
        return redirect()->route('workflows.index', $workflow->workspace_id)
            ->with('success', 'Workflow deleted successfully');
    }

    /**
     * Get workflow templates.
     */
    public function getTemplates(Request $request)
    {
        $templates = WorkflowTemplate::with(['steps'])
            ->where('is_public', true)
            ->orWhere('created_by', Auth::id())
            ->orderBy('name')
            ->get();
            
        $categories = WorkflowTemplate::distinct()
            ->pluck('category')
            ->filter()
            ->values();
            
        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'templates' => $templates,
                'categories' => $categories
            ]);
        }
        
        return view('workflows.templates', compact('templates', 'categories'));
    }

    /**
     * Import a workflow template.
     */
    public function importTemplate($templateId, Request $request)
    {
        $template = WorkflowTemplate::with(['steps'])->findOrFail($templateId);
        
        // Check if user has access to this template
        if (!$template->is_public && $template->created_by !== Auth::id()) {
            return response()->json(['error' => 'Template not found'], 404);
        }
        
        $validated = $request->validate([
            'workspace_id' => 'required|exists:workspaces,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);
        
        $workspace = Workspace::findOrFail($validated['workspace_id']);
        $this->authorize('create', [Workflow::class, $workspace]);
        
        DB::transaction(function () use ($template, $validated) {
            // Create the workflow
            $workflow = Workflow::create([
                'workspace_id' => $validated['workspace_id'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? $template->description,
                'is_active' => true,
                'template_id' => $template->id,
                'created_by' => Auth::id()
            ]);
            
            // Create steps from template
            foreach ($template->steps as $templateStep) {
                WorkflowStep::create([
                    'workflow_id' => $workflow->id,
                    'name' => $templateStep->name,
                    'type' => $templateStep->type,
                    'step_order' => $templateStep->step_order,
                    'assigned_to_type' => $templateStep->assigned_to_type,
                    'assigned_to_id' => $templateStep->assigned_to_id,
                    'assigned_role_id' => $templateStep->assigned_role_id,
                    'time_limit_days' => $templateStep->time_limit_days,
                    'notify_assignee' => $templateStep->notify_assignee,
                    'is_required' => $templateStep->is_required,
                    'created_by' => Auth::id()
                ]);
            }
            
            // Log template import
            DB::table('audit_logs')->insert([
                'workspace_id' => $validated['workspace_id'],
                'actor_user_id' => Auth::id(),
                'action' => 'workflow_imported',
                'resource_type' => 'workflow',
                'resource_id' => $workflow->id,
                'metadata' => json_encode([
                    'name' => $workflow->name,
                    'template' => $template->name
                ]),
                'created_at' => now()
            ]);
            
            return $workflow;
        });
        
        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Template imported successfully',
                'workflow' => $workflow->load('steps')
            ], 201);
        }
        
        return redirect()->route('workflows.show', $workflow->id)
            ->with('success', 'Template imported successfully');
    }

    /**
     * Get activity for a workflow.
     */
    public function getActivity($workflowId)
    {
        $workflow = Workflow::findOrFail($workflowId);
        $this->authorize('view', $workflow->workspace);
        
        $activities = WorkflowActivity::with(['user', 'instance.document'])
            ->where('workflow_id', $workflowId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        $stats = [
            'total_instances' => WorkflowInstance::where('workflow_id', $workflowId)->count(),
            'completed_instances' => WorkflowInstance::where('workflow_id', $workflowId)
                ->where('status', 'completed')
                ->count(),
            'active_instances' => WorkflowInstance::where('workflow_id', $workflowId)
                ->where('status', 'in_progress')
                ->count(),
            'average_completion_time' => WorkflowInstance::where('workflow_id', $workflowId)
                ->where('status', 'completed')
                ->avg(DB::raw('TIMESTAMPDIFF(HOUR, started_at, completed_at)'))
        ];
        
        if (request()->wantsJson() || request()->is('api/*')) {
            return response()->json([
                'activities' => $activities,
                'stats' => $stats
            ]);
        }
        
        return view('workflows.activity', compact('activities', 'stats', 'workflow'));
    }

    /**
     * Store a newly created workflow.
     */
    public function store(Request $request, $workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('create', [Workflow::class, $workspace]);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'steps' => 'required|array|min:1',
            'steps.*.name' => 'required|string|max:255',
            'steps.*.type' => 'required|in:approval,review,notification',
            'steps.*.assigned_to_type' => 'required|in:user,role,specific',
            'steps.*.assigned_to_id' => 'nullable|exists:users,id',
            'steps.*.assigned_role_id' => 'nullable|exists:roles,id',
            'steps.*.time_limit_days' => 'nullable|integer|min:1',
            'steps.*.notify_assignee' => 'sometimes|boolean',
            'steps.*.is_required' => 'sometimes|boolean'
        ]);
        
        $workflow = DB::transaction(function () use ($workspaceId, $validated) {
            // Create the workflow
            $workflow = Workflow::create([
                'workspace_id' => $workspaceId,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => true,
                'created_by' => Auth::id()
            ]);
            
            // Create steps
            foreach ($validated['steps'] as $index => $stepData) {
                WorkflowStep::create([
                    'workflow_id' => $workflow->id,
                    'name' => $stepData['name'],
                    'type' => $stepData['type'],
                    'step_order' => $index + 1,
                    'assigned_to_type' => $stepData['assigned_to_type'],
                    'assigned_to_id' => $stepData['assigned_to_id'] ?? null,
                    'assigned_role_id' => $stepData['assigned_role_id'] ?? null,
                    'time_limit_days' => $stepData['time_limit_days'] ?? null,
                    'notify_assignee' => $stepData['notify_assignee'] ?? true,
                    'is_required' => $stepData['is_required'] ?? true,
                    'created_by' => Auth::id()
                ]);
            }
            
            // Log workflow creation
            DB::table('audit_logs')->insert([
                'workspace_id' => $workspaceId,
                'actor_user_id' => Auth::id(),
                'action' => 'workflow_created',
                'resource_type' => 'workflow',
                'resource_id' => $workflow->id,
                'metadata' => json_encode(['name' => $validated['name']]),
                'created_at' => now()
            ]);
            
            return $workflow;
        });
        
        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json($workflow->load('steps'), 201);
        }
        
        return redirect()->route('workflows.show', $workflow->id)
            ->with('success', 'Workflow created successfully');
    }
}