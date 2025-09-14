<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Workspace;
use App\Models\User;
use App\Models\TaskComment;
use App\Models\TaskAssignee;
use App\Models\AuditLog;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TasksController extends Controller
{
    // Core CRUD & Actions
    
    /**
     * Display tasks for a specific workspace
     */
    public function index($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('view', $workspace);
        
        // Get summary stats
        $summary = [
            'total' => Task::where('workspace_id', $workspaceId)->count(),
            'open' => Task::where('workspace_id', $workspaceId)->where('status', 'open')->count(),
            'in_progress' => Task::where('workspace_id', $workspaceId)->where('status', 'in_progress')->count(),
            'completed' => Task::where('workspace_id', $workspaceId)->where('status', 'completed')->count(),
            'cancelled' => Task::where('workspace_id', $workspaceId)->where('status', 'cancelled')->count(),
        ];
        
        // Get filtered tasks
        $query = Task::with(['assignee', 'creator', 'document'])
            ->where('workspace_id', $workspaceId);
            
        // Apply filters
        if (request('status') && request('status') !== 'all') {
            $query->where('status', request('status'));
        }
        
        if (request('priority') && request('priority') !== 'all') {
            $query->where('priority', request('priority'));
        }
        
        if (request('q')) {
            $query->where('title', 'like', '%' . request('q') . '%');
        }
        
        // Apply sorting
        switch (request('sort', 'updated_desc')) {
            case 'updated_desc':
                $query->orderBy('updated_at', 'desc');
                break;
            case 'due_asc':
                $query->orderBy('due_at', 'asc');
                break;
            case 'title_asc':
                $query->orderBy('title', 'asc');
                break;
        }
        
        $tasks = $query->paginate(20);
        
        if (request()->header('HX-Request')) {
            return view('partials.tasks.list', [
                'tasks' => $tasks
            ]);
        }
            
        return view('tasks.index', [
            'tasks' => $tasks,
            'workspace' => $workspace,
            'summary' => (object)$summary
        ]);
    }

    /**
     * Show task creation form
     */
    public function create()
    {
        $workspaces = auth()->user()->accessibleWorkspaces();
        return view('tasks.select-workspace', compact('workspaces'));
    }

    /**
     * Display a specific task
     */
    public function show($taskId)
    {
        $task = Task::with([
            'assignee', 
            'creator', 
            'document', 
            'comments.author',
            'assignees.user'
        ])->findOrFail($taskId);
        
        $this->authorize('view', $task);
        
        return view('tasks.show', compact('task'));
    }

    /**
     * Update a task
     */
    public function update(Request $request, $taskId)
    {
        $task = Task::findOrFail($taskId);
        $this->authorize('update', $task);
        
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'due_at' => 'nullable|date',
            'priority' => 'sometimes|in:low,medium,high',
            'status' => 'sometimes|in:open,in_progress,completed,cancelled',
            'assigned_to' => 'nullable|exists:users,id'
        ]);
        
        $task->update($validated);
        
        // Log the update
        AuditLog::create([
            'workspace_id' => $task->workspace_id,
            'actor_user_id' => Auth::id(),
            'action' => 'task.updated',
            'resource_type' => 'task',
            'resource_id' => $task->id,
            'metadata' => json_encode($validated)
        ]);
        
        if ($request->header('HX-Request')) {
            return view('partials.tasks.list', ['tasks' => collect([$task])]);
        }
        
        return redirect()->route('tasks.show', $task->id)
            ->with('success', 'Task updated successfully');
    }

    /**
     * Delete a task
     */
    public function delete($taskId)
    {
        $task = Task::findOrFail($taskId);
        $this->authorize('delete', $task);
        
        // Log the deletion
        AuditLog::create([
            'workspace_id' => $task->workspace_id,
            'actor_user_id' => Auth::id(),
            'action' => 'task.deleted',
            'resource_type' => 'task',
            'resource_id' => $task->id,
            'metadata' => json_encode(['title' => $task->title])
        ]);
        
        $task->delete();
        
        if (request()->header('HX-Request')) {
            return response()->json(['success' => true]);
        }
        
        return redirect()->route('tasks.index', $task->workspace_id)
            ->with('success', 'Task deleted successfully');
    }

    /**
     * Get activity for a task
     */
    public function getActivity($taskId)
    {
        $task = Task::findOrFail($taskId);
        $this->authorize('view', $task);
        
        $activities = AuditLog::where('resource_type', 'task')
            ->where('resource_id', $taskId)
            ->with('actor')
            ->orderBy('created_at', 'desc')
            ->get();
            
        if (request()->header('HX-Request')) {
            return view('tasks.partials.activity-feed', compact('activities'));
        }
        
        return response()->json($activities);
    }

    /**
     * Assign a task to a user
     */
    public function assign(Request $request, $taskId)
    {
        $task = Task::findOrFail($taskId);
        $this->authorize('update', $task);
        
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);
        
        // For single assignee tasks
        $task->update(['assigned_to' => $validated['user_id']]);
        
        // For multiple assignees (if using task_assignees table)
        // TaskAssignee::updateOrCreate(
        //     ['task_id' => $taskId],
        //     ['user_id' => $validated['user_id']]
        // );
        
        AuditLog::create([
            'workspace_id' => $task->workspace_id,
            'actor_user_id' => Auth::id(),
            'action' => 'task.assigned',
            'resource_type' => 'task',
            'resource_id' => $task->id,
            'metadata' => json_encode(['assigned_to' => $validated['user_id']])
        ]);
        
        if ($request->header('HX-Request')) {
            $task->load('assignee');
            return view('tasks.partials.assignee', compact('task'));
        }
        
        return redirect()->back()->with('success', 'Task assigned successfully');
    }

    /**
     * Add a comment to a task
     */
    public function addComment(Request $request, $taskId)
    {
        $task = Task::findOrFail($taskId);
        $this->authorize('view', $task);
        
        $validated = $request->validate([
            'body' => 'required|string|max:1000'
        ]);
        
        $comment = TaskComment::create([
            'task_id' => $taskId,
            'author_id' => Auth::id(),
            'body' => $validated['body']
        ]);
        
        AuditLog::create([
            'workspace_id' => $task->workspace_id,
            'actor_user_id' => Auth::id(),
            'action' => 'task.comment.added',
            'resource_type' => 'task',
            'resource_id' => $task->id
        ]);
        
        if ($request->header('HX-Request')) {
            $comment->load('author');
            return view('tasks.partials.comment', compact('comment'));
        }
        
        return redirect()->back()->with('success', 'Comment added successfully');
    }

    /**
     * Update task status
     */
    public function updateStatus(Request $request, $taskId)
    {
        $task = Task::findOrFail($taskId);
        $this->authorize('update', $task);
        
        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,completed,cancelled'
        ]);
        
        $previousStatus = $task->status;
        $task->update([
            'status' => $validated['status'],
            'completed_at' => $validated['status'] === 'completed' ? now() : null
        ]);
        
        AuditLog::create([
            'workspace_id' => $task->workspace_id,
            'actor_user_id' => Auth::id(),
            'action' => 'task.status.updated',
            'resource_type' => 'task',
            'resource_id' => $task->id,
            'metadata' => json_encode([
                'from' => $previousStatus,
                'to' => $validated['status']
            ])
        ]);
        
        if ($request->header('HX-Request')) {
            return view('tasks.partials.status-badge', compact('task'));
        }
        
        return redirect()->back()->with('success', 'Task status updated');
    }

    /**
     * Update status via HTMX inline edit
     */
    public function inlineStatus(Request $request, $taskId)
    {
        $task = Task::findOrFail($taskId);
        $this->authorize('update', $task);
        
        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,completed,cancelled'
        ]);
        
        $previousStatus = $task->status;
        $task->update([
            'status' => $validated['status'],
            'completed_at' => $validated['status'] === 'completed' ? now() : null
        ]);
        
        return view('partials.tasks.list', ['tasks' => collect([$task])]);
    }

    // Workflow & Approvals 
    
    /**
     * Create approval workflow for a task
     */
    public function createApprovalWorkflow(Request $request, $taskId)
    {
        $task = Task::findOrFail($taskId);
        $this->authorize('update', $task);
        
        $validated = $request->validate([
            'steps' => 'required|array',
            'steps.*.approver_id' => 'required|exists:users,id',
            'steps.*.order' => 'required|integer|min:1'
        ]);
        
        // Implementation would depend on your workflow table structure
        // This is a placeholder for the workflow creation logic
        
        AuditLog::create([
            'workspace_id' => $task->workspace_id,
            'actor_user_id' => Auth::id(),
            'action' => 'task.workflow.created',
            'resource_type' => 'task',
            'resource_id' => $task->id
        ]);
        
        return response()->json(['message' => 'Workflow created successfully']);
    }

    /**
     * Get workflow steps for a task
     */
    public function getWorkflowSteps($taskId)
    {
        $task = Task::findOrFail($taskId);
        $this->authorize('view', $task);
        
        // This would query your workflow steps table
        $steps = []; // Placeholder
        
        return response()->json($steps);
    }

    /**
     * Advance workflow to next step
     */
    public function advanceWorkflow(Request $request, $taskId)
    {
        $task = Task::findOrFail($taskId);
        $this->authorize('update', $task);
        
        $validated = $request->validate([
            'decision' => 'required|in:approved,rejected',
            'notes' => 'nullable|string'
        ]);
        
        // Implementation would depend on your workflow system
        // This would typically update the current step status and move to next
        
        AuditLog::create([
            'workspace_id' => $task->workspace_id,
            'actor_user_id' => Auth::id(),
            'action' => 'task.workflow.advanced',
            'resource_type' => 'task',
            'resource_id' => $task->id,
            'metadata' => json_encode($validated)
        ]);
        
        return response()->json(['message' => 'Workflow advanced']);
    }

    /**
     * Delegate approval to another user
     */
    public function delegateApproval(Request $request, $taskId)
    {
        $task = Task::findOrFail($taskId);
        $this->authorize('update', $task);
        
        $validated = $request->validate([
            'delegate_to' => 'required|exists:users,id',
            'reason' => 'nullable|string'
        ]);
        
        // Implementation would update the workflow to assign to another user
        
        AuditLog::create([
            'workspace_id' => $task->workspace_id,
            'actor_user_id' => Auth::id(),
            'action' => 'task.approval.delegated',
            'resource_type' => 'task',
            'resource_id' => $task->id,
            'metadata' => json_encode($validated)
        ]);
        
        return response()->json(['message' => 'Approval delegated successfully']);
    }

    // HTMX Fragments & Cards
    
    /**
     * Task summary for dashboard
     */
    public function summary()
    {
        $userId = Auth::id();
        
        $stats = [
            'total' => Task::where('assigned_to', $userId)->count(),
            'completed' => Task::where('assigned_to', $userId)->where('status', 'completed')->count(),
            'overdue' => Task::where('assigned_to', $userId)
                ->where('status', '!=', 'completed')
                ->where('due_at', '<', now())
                ->count()
        ];
        
        if (request()->header('HX-Request')) {
            return view('dashboard.partials.tasks-summary', compact('stats'));
        }
        
        return response()->json($stats);
    }

    /**
     * Workspace task summary card
     */
    public function summaryCard($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('view', $workspace);
        
        $stats = [
            'total' => Task::where('workspace_id', $workspaceId)->count(),
            'completed' => Task::where('workspace_id', $workspaceId)
                ->where('status', 'completed')->count(),
            'in_progress' => Task::where('workspace_id', $workspaceId)
                ->where('status', 'in_progress')->count()
        ];
        
        return view('partials.tasks.summary-card', compact('stats', 'workspace'));
    }

    /**
     * Task controls (filters, sort options)
     */
    public function controls($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('view', $workspace);
        
        return view('partials.tasks.controls', compact('workspace'));
    }

    /**
     * Task list fragment for HTMX
     */
    public function list($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('view', $workspace);
        
        $query = Task::with(['assignee', 'creator'])
            ->where('workspace_id', $workspaceId);
            
        // Apply filters
        if (request('status') && request('status') !== 'all') {
            $query->where('status', request('status'));
        }
        
        if (request('priority') && request('priority') !== 'all') {
            $query->where('priority', request('priority'));
        }
        
        if (request('q')) {
            $query->where('title', 'like', '%' . request('q') . '%');
        }
        
        // Apply sorting
        switch (request('sort', 'updated_desc')) {
            case 'updated_desc':
                $query->orderBy('updated_at', 'desc');
                break;
            case 'due_asc':
                $query->orderBy('due_at', 'asc');
                break;
            case 'title_asc':
                $query->orderBy('title', 'asc');
                break;
        }
        
        $tasks = $query->paginate(20);
        
        return view('partials.tasks.list', compact('tasks'));
    }

    /**
     * My tasks card for dashboard
     */
    public function myTasksCard()
    {
        $userId = Auth::id();
        
        $tasks = Task::with(['workspace'])
            ->where('assigned_to', $userId)
            ->where('status', '!=', 'completed')
            ->orderBy('due_at', 'asc')
            ->limit(5)
            ->get();
            
        return view('dashboard.partials.my-tasks-card', compact('tasks'));
    }

    /**
     * Task creation form fragment
     */
    public function createForm($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('create', [Task::class, $workspace]);
        
        $users = $workspace->members()->with('user')->get()->pluck('user');
        $documents = Document::where('workspace_id', $workspaceId)
            ->where('is_deleted', false)
            ->get();
        
        return view('partials.tasks.create-modal', compact('workspace', 'users', 'documents'));
    }

    /**
     * Store a new task
     */
    public function store(Request $request, $workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('create', [Task::class, $workspace]);
        
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_at' => 'nullable|date',
            'priority' => 'required|in:low,medium,high',
            'status' => 'required|in:open,in_progress,completed,cancelled',
            'assigned_to' => 'nullable|exists:users,id',
            'document_id' => 'nullable|exists:documents,id'
        ]);
        
        $task = Task::create(array_merge($validated, [
            'workspace_id' => $workspaceId,
            'created_by' => Auth::id()
        ]));
        
        AuditLog::create([
            'workspace_id' => $workspaceId,
            'actor_user_id' => Auth::id(),
            'action' => 'task.created',
            'resource_type' => 'task',
            'resource_id' => $task->id,
            'metadata' => json_encode(['title' => $task->title])
        ]);
        
        if ($request->header('HX-Request')) {
            // Return the new task list
            $tasks = Task::where('workspace_id', $workspaceId)
                ->orderBy('created_at', 'desc')
                ->paginate(20);
                
            return view('partials.tasks.list', compact('tasks'));
        }
        
        return redirect()->route('tasks.show', $task->id)
            ->with('success', 'Task created successfully');
    }

    /**
     * Web index for tasks with HTMX support.
     */
    public function webIndex($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('view', $workspace);
        
        $tasks = Task::with(['assignee', 'createdBy', 'document'])
            ->where('workspace_id', $workspaceId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        if (request()->wantsJson() || request()->hasHeader('HX-Request')) {
            return view('partials.tasks.list', compact('tasks'));
        }

        // Get summary stats
        $summary = [
            'total' => Task::where('workspace_id', $workspaceId)->count(),
            'open' => Task::where('workspace_id', $workspaceId)->where('status', 'open')->count(),
            'in_progress' => Task::where('workspace_id', $workspaceId)->where('status', 'in_progress')->count(),
            'completed' => Task::where('workspace_id', $workspaceId)->where('status', 'completed')->count(),
            'cancelled' => Task::where('workspace_id', $workspaceId)->where('status', 'cancelled')->count(),
        ];

        return view('tasks.index', compact('tasks', 'workspace', 'summary'));
    }

    /**
     * Web show for tasks with HTMX support.
     */
    public function webShow($workspaceId, $taskId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('view', $workspace);
        
        $task = Task::with(['assignee', 'createdBy', 'document', 'comments.author'])
            ->where('workspace_id', $workspaceId)
            ->findOrFail($taskId);

        if (request()->wantsJson() || request()->hasHeader('HX-Request')) {
            return response()->json($task);
        }

        return view('tasks.show', compact('task', 'workspace'));
    }

    /**
     * Web create form for tasks.
     */
    public function webCreate($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('create', [Task::class, $workspace]);
        
        $users = $workspace->members()->with('user')->get()->pluck('user');
        $documents = Document::where('workspace_id', $workspaceId)
            ->where('is_deleted', false)
            ->get();

        if (request()->hasHeader('HX-Request')) {
            return view('partials.tasks.create-modal', compact('users', 'documents', 'workspace'));
        }

        return view('tasks.create', compact('users', 'documents', 'workspace'));
    }

    /**
     * Web edit form for tasks.
     */
    public function webEdit($workspaceId, $taskId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('update', $workspace);
        
        $task = Task::where('workspace_id', $workspaceId)
            ->findOrFail($taskId);
        
        $users = $workspace->members()->with('user')->get()->pluck('user');
        $documents = Document::where('workspace_id', $workspaceId)
            ->where('is_deleted', false)
            ->get();

        if (request()->hasHeader('HX-Request')) {
            return view('tasks.partials.edit-form', compact('task', 'users', 'documents', 'workspace'));
        }

        return view('tasks.edit', compact('task', 'users', 'documents', 'workspace'));
    }
}