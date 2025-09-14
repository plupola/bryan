<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Models\FileRequest;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FileRequestsController extends Controller
{
    /**
     * Display a listing of file requests for a workspace.
     */
    public function index($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('view', $workspace);
        
        $fileRequests = FileRequest::where('workspace_id', $workspaceId)
            ->with(['folder', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        return view('file-requests.index', [
            'workspace' => $workspace,
            'fileRequests' => $fileRequests
        ]);
    }

    /**
     * Show the form for creating a new file request.
     */
    public function createForm($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('create', [FileRequest::class, $workspace]);
        
        $folders = Folder::where('workspace_id', $workspaceId)
            ->where('is_deleted', 0)
            ->orderBy('path')
            ->get();
            
        return view('file-requests.create', [
            'workspace' => $workspace,
            'folders' => $folders
        ]);
    }

    /**
     * Store a newly created file request.
     */
    public function store(Request $request, $workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('create', [FileRequest::class, $workspace]);
        
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'instructions' => 'nullable|string',
            'folder_id' => 'nullable|exists:folders,id,workspace_id,' . $workspaceId,
            'opens_at' => 'nullable|date',
            'closes_at' => 'nullable|date|after:opens_at',
            'require_email' => 'boolean'
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        try {
            $fileRequest = FileRequest::create([
                'workspace_id' => $workspaceId,
                'folder_id' => $request->folder_id,
                'created_by' => auth()->id(),
                'title' => $request->title,
                'instructions' => $request->instructions,
                'token' => Str::random(32),
                'opens_at' => $request->opens_at,
                'closes_at' => $request->closes_at,
                'require_email' => $request->require_email ?? true,
            ]);
            
            return redirect()->route('file-requests.show', [
                'workspaceId' => $workspaceId,
                'fileRequest' => $fileRequest->id
            ])->with('success', 'File request created successfully.');
            
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to create file request: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Handle file upload for a file request.
     */
    public function upload($requestId)
    {
        $fileRequest = FileRequest::with('workspace')->findOrFail($requestId);
        
        // Check if file request is active
        $now = now();
        if ($fileRequest->opens_at && $now->lt($fileRequest->opens_at)) {
            return response()->json(['error' => 'File request is not open yet'], 403);
        }
        
        if ($fileRequest->closes_at && $now->gt($fileRequest->closes_at)) {
            return response()->json(['error' => 'File request has closed'], 403);
        }
        
        return view('file-requests.upload', [
            'fileRequest' => $fileRequest
        ]);
    }

    /**
     * Display file request statistics for a workspace.
     */
    public function stats($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('view', $workspace);
        
        $stats = FileRequest::where('workspace_id', $workspaceId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN opens_at > NOW() THEN 1 ELSE 0 END) as scheduled')
            ->selectRaw('SUM(CASE WHEN opens_at <= NOW() AND (closes_at IS NULL OR closes_at >= NOW()) THEN 1 ELSE 0 END) as active')
            ->selectRaw('SUM(CASE WHEN closes_at < NOW() THEN 1 ELSE 0 END) as expired')
            ->first();
            
        $recentActivity = DB::table('documents')
            ->join('file_requests', 'documents.folder_id', '=', 'file_requests.folder_id')
            ->where('file_requests.workspace_id', $workspaceId)
            ->where('documents.created_at', '>=', now()->subDays(7))
            ->select('documents.original_name', 'documents.created_at')
            ->orderBy('documents.created_at', 'desc')
            ->limit(10)
            ->get();
            
        return view('file-requests.stats', [
            'workspace' => $workspace,
            'stats' => $stats,
            'recentActivity' => $recentActivity
        ]);
    }

    /**
     * Get filters for file requests.
     */
    public function filters($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('view', $workspace);
        
        $statuses = [
            'active' => 'Active',
            'scheduled' => 'Scheduled',
            'expired' => 'Expired'
        ];
        
        $folders = Folder::where('workspace_id', $workspaceId)
            ->where('is_deleted', 0)
            ->pluck('name', 'id');
            
        return response()->json([
            'statuses' => $statuses,
            'folders' => $folders
        ]);
    }

    /**
     * Get filtered list of file requests (for HTMX).
     */
    public function list($workspaceId, Request $request)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $this->authorize('view', $workspace);
        
        $query = FileRequest::where('workspace_id', $workspaceId)
            ->with(['folder', 'createdBy']);
            
        // Apply filters
        if ($request->has('status')) {
            $now = now();
            switch ($request->status) {
                case 'active':
                    $query->where('opens_at', '<=', $now)
                        ->where(function($q) use ($now) {
                            $q->whereNull('closes_at')
                                ->orWhere('closes_at', '>=', $now);
                        });
                    break;
                case 'scheduled':
                    $query->where('opens_at', '>', $now);
                    break;
                case 'expired':
                    $query->where('closes_at', '<', $now);
                    break;
            }
        }
        
        if ($request->has('folder_id')) {
            $query->where('folder_id', $request->folder_id);
        }
        
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }
        
        $fileRequests = $query->orderBy('created_at', 'desc')->paginate(20);
        
        return view('file-requests.partials.list', [
            'fileRequests' => $fileRequests
        ]);
    }

    /**
     * Show the create form (alternative to createForm).
     */
    public function create()
    {
        // This would typically redirect to createForm or handle workspace selection
        $workspaces = auth()->user()->accessibleWorkspaces();
        return view('file-requests.select-workspace', compact('workspaces'));
    }
    
}