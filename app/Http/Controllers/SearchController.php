<?php

namespace App\Http\Controllers;

use App\Models\{
    Document, Folder, Workspace, User, Comment, 
    AuditLog, ViewHistory, DownloadHistory
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SearchController extends Controller
{
    /**
     * Global search across all accessible workspaces
     */
    public function global(Request $request)
    {
        $user = auth()->user();
        $query = $request->input('q', '');
        $type = $request->input('type', 'all');
        $page = $request->input('page', 1);
        $perPage = 20;

        if (empty($query)) {
            return response()->json(['results' => [], 'total' => 0]);
        }

        // Get accessible workspace IDs
        $workspaceIds = DB::table('workspace_members')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->pluck('workspace_id');

        $results = collect();

        // Search documents
        if (in_array($type, ['all', 'documents'])) {
            $documents = Document::whereIn('workspace_id', $workspaceIds)
                ->where('is_deleted', false)
                ->where(function($q) use ($query) {
                    $q->where('original_name', 'LIKE', "%{$query}%")
                      ->orWhere('description', 'LIKE', "%{$query}%");
                })
                ->with(['folder', 'workspace'])
                ->orderBy('updated_at', 'desc')
                ->paginate($perPage, ['*'], 'documents_page', $page);

            $results = $results->merge($documents->items());
        }

        // Search folders
        if (in_array($type, ['all', 'folders'])) {
            $folders = Folder::whereIn('workspace_id', $workspaceIds)
                ->where('is_deleted', false)
                ->where('name', 'LIKE', "%{$query}%")
                ->with('workspace')
                ->orderBy('updated_at', 'desc')
                ->paginate($perPage, ['*'], 'folders_page', $page);

            $results = $results->merge($folders->items());
        }

        // Search comments
        if (in_array($type, ['all', 'comments'])) {
            $comments = Comment::whereIn('workspace_id', $workspaceIds)
                ->where('body', 'LIKE', "%{$query}%")
                ->with(['author', 'resource'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'comments_page', $page);

            $results = $results->merge($comments->items());
        }

        return response()->json([
            'results' => $results,
            'total' => $results->count(),
            'query' => $query
        ]);
    }

    /**
     * System-wide activity feed
     */
    public function activityFeed(Request $request)
    {
        $user = auth()->user();
        $page = $request->input('page', 1);
        $perPage = 30;
        
        // Get accessible workspace IDs
        $workspaceIds = DB::table('workspace_members')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->pluck('workspace_id');

        $activities = AuditLog::whereIn('workspace_id', $workspaceIds)
            ->orWhereNull('workspace_id') // System activities
            ->with(['actor', 'workspace'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'activities' => $activities->items(),
            'total' => $activities->total(),
            'current_page' => $activities->currentPage()
        ]);
    }

    /**
     * Workspace-specific activity feed
     */
    public function workspaceActivity(Request $request, $workspaceId)
    {
        $user = auth()->user();
        
        // Verify user has access to this workspace
        $isMember = DB::table('workspace_members')
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if (!$isMember) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $page = $request->input('page', 1);
        $perPage = 30;
        $filter = $request->input('filter', 'all');

        $query = AuditLog::where('workspace_id', $workspaceId)
            ->with(['actor', 'workspace']);

        if ($filter !== 'all') {
            $query->where('action', 'LIKE', "%{$filter}%");
        }

        $activities = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'activities' => $activities->items(),
            'total' => $activities->total(),
            'current_page' => $activities->currentPage()
        ]);
    }

    /**
     * Quick search for autocomplete
     */
    public function quick(Request $request)
    {
        $user = auth()->user();
        $query = $request->input('q', '');
        
        if (empty($query)) {
            return response()->json([]);
        }

        // Get accessible workspace IDs
        $workspaceIds = DB::table('workspace_members')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->pluck('workspace_id');

        $results = [];

        // Quick document search
        $documents = Document::whereIn('workspace_id', $workspaceIds)
            ->where('is_deleted', false)
            ->where('original_name', 'LIKE', "{$query}%")
            ->limit(5)
            ->get(['id', 'original_name', 'workspace_id']);

        foreach ($documents as $doc) {
            $results[] = [
                'type' => 'document',
                'id' => $doc->id,
                'name' => $doc->original_name,
                'workspace_id' => $doc->workspace_id
            ];
        }

        // Quick folder search
        $folders = Folder::whereIn('workspace_id', $workspaceIds)
            ->where('is_deleted', false)
            ->where('name', 'LIKE', "{$query}%")
            ->limit(5)
            ->get(['id', 'name', 'workspace_id']);

        foreach ($folders as $folder) {
            $results[] = [
                'type' => 'folder',
                'id' => $folder->id,
                'name' => $folder->name,
                'workspace_id' => $folder->workspace_id
            ];
        }

        return response()->json($results);
    }

    /**
     * Advanced search with filters
     */
    public function advancedSearch(Request $request)
    {
        $user = auth()->user();
        $filters = $request->all();

        // Get accessible workspace IDs
        $workspaceIds = DB::table('workspace_members')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->pluck('workspace_id');

        $query = Document::whereIn('workspace_id', $workspaceIds)
            ->where('is_deleted', false)
            ->with(['folder', 'workspace', 'uploadedBy']);

        // Apply filters
        if (!empty($filters['query'])) {
            $query->where('original_name', 'LIKE', "%{$filters['query']}%");
        }

        if (!empty($filters['workspace_id'])) {
            $query->where('workspace_id', $filters['workspace_id']);
        }

        if (!empty($filters['folder_id'])) {
            $query->where('folder_id', $filters['folder_id']);
        }

        if (!empty($filters['uploaded_by'])) {
            $query->where('uploaded_by', $filters['uploaded_by']);
        }

        if (!empty($filters['mime_type'])) {
            $query->where('mime_type', 'LIKE', "%{$filters['mime_type']}%");
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['size_min'])) {
            $query->where('size_bytes', '>=', $filters['size_min']);
        }

        if (!empty($filters['size_max'])) {
            $query->where('size_bytes', '<=', $filters['size_max']);
        }

        $results = $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 20);

        return response()->json([
            'results' => $results->items(),
            'total' => $results->total(),
            'filters' => $filters
        ]);
    }

    /**
     * Save a search query for later use
     */
    public function saveSearchQuery(Request $request)
    {
        $user = auth()->user();
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'query_params' => 'required|json',
            'is_global' => 'boolean'
        ]);

        $savedSearch = DB::table('saved_searches')->insert([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'query_params' => $validated['query_params'],
            'is_global' => $validated['is_global'] ?? false,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'saved_search' => $savedSearch
        ]);
    }

    /**
     * Get user's saved searches
     */
    public function getSavedSearches(Request $request)
    {
        $user = auth()->user();
        
        $searches = DB::table('saved_searches')
            ->where('user_id', $user->id)
            ->orWhere('is_global', true)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($searches);
    }

    /**
     * Search within previous search results
     */
    public function searchWithinResults(Request $request)
    {
        $user = auth()->user();
        $previousResults = $request->input('previous_results', []);
        $newQuery = $request->input('query', '');
        
        if (empty($previousResults) || empty($newQuery)) {
            return response()->json(['results' => []]);
        }

        // Filter previous results based on new query
        $filteredResults = array_filter($previousResults, function($item) use ($newQuery) {
            return stripos($item['name'] ?? '', $newQuery) !== false ||
                   stripos($item['description'] ?? '', $newQuery) !== false;
        });

        return response()->json([
            'results' => array_values($filteredResults),
            'original_count' => count($previousResults),
            'filtered_count' => count($filteredResults)
        ]);
    }

    /**
     * Get search analytics for admin dashboard
     */
    public function getSearchAnalytics(Request $request)
    {
        $user = auth()->user();
        
        // Check if user has admin privileges
        if (!$user->hasRole('system_admin')) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $period = $request->input('period', '7days'); // 7days, 30days, 90days
        
        $dateRange = match($period) {
            '30days' => [now()->subDays(30), now()],
            '90days' => [now()->subDays(90), now()],
            default => [now()->subDays(7), now()],
        };

        // Get popular search terms
        $popularSearches = DB::table('search_history')
            ->whereBetween('created_at', $dateRange)
            ->select('query', DB::raw('COUNT(*) as count'))
            ->groupBy('query')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        // Get search frequency over time
        $searchFrequency = DB::table('search_history')
            ->whereBetween('created_at', $dateRange)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Get most successful searches (those that lead to clicks)
        $successfulSearches = DB::table('search_history AS s')
            ->join('view_history AS v', function($join) {
                $join->on('s.user_id', '=', 'v.user_id')
                     ->whereRaw('v.created_at BETWEEN s.created_at AND DATE_ADD(s.created_at, INTERVAL 1 HOUR)');
            })
            ->whereBetween('s.created_at', $dateRange)
            ->select('s.query', DB::raw('COUNT(DISTINCT v.id) as click_count'))
            ->groupBy('s.query')
            ->orderBy('click_count', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'popular_searches' => $popularSearches,
            'search_frequency' => $searchFrequency,
            'successful_searches' => $successfulSearches,
            'period' => $period
        ]);
    }
}