<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Workspace;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CommentsController extends Controller
{
    /**
     * Display a listing of comments for a specific resource.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'resource_type' => 'required|in:document,document_version,folder',
            'resource_id' => 'required|integer',
            'workspace_id' => 'required|exists:workspaces,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if user has access to the workspace
        $workspace = Workspace::findOrFail($request->workspace_id);
        if (!$workspace->members()->where('user_id', Auth::id())->exists()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $comments = Comment::with(['author', 'replies', 'reactions'])
            ->where('workspace_id', $request->workspace_id)
            ->where('resource_type', $request->resource_type)
            ->where('resource_id', $request->resource_id)
            ->whereNull('parent_id')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json(['comments' => $comments]);
    }

    /**
     * Store a newly created comment.
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'resource_type' => 'required|in:document,document_version,folder',
            'resource_id' => 'required|integer',
            'workspace_id' => 'required|exists:workspaces,id',
            'body' => 'required|string|max:5000',
            'parent_id' => 'nullable|exists:comments,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if user has access to the workspace and permission to comment
        $workspace = Workspace::findOrFail($request->workspace_id);
        $member = $workspace->members()->where('user_id', Auth::id())->first();
        
        if (!$member) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Check ACL permissions for commenting (simplified)
        if (!$this->hasCommentPermission($member, $request->resource_type, $request->resource_id)) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        try {
            DB::beginTransaction();

            $comment = Comment::create([
                'workspace_id' => $request->workspace_id,
                'resource_type' => $request->resource_type,
                'resource_id' => $request->resource_id,
                'author_id' => Auth::id(),
                'parent_id' => $request->parent_id,
                'body' => $request->body,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create notification for mentioned users (if any)
            $this->handleMentions($comment, $request->body);

            DB::commit();

            return response()->json([
                'message' => 'Comment added successfully',
                'comment' => $comment->load('author')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create comment'], 500);
        }
    }

    /**
     * Update the specified comment.
     */
    public function update(Request $request, $commentId)
    {
        $comment = Comment::findOrFail($commentId);

        // Check if user is the author of the comment
        if ($comment->author_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'body' => 'required|string|max:5000'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $comment->update([
            'body' => $request->body,
            'updated_at' => now()
        ]);

        return response()->json([
            'message' => 'Comment updated successfully',
            'comment' => $comment
        ]);
    }

    /**
     * Remove the specified comment.
     */
    public function delete($commentId)
    {
        $comment = Comment::findOrFail($commentId);

        // Check if user is the author or has admin privileges
        $isAuthor = $comment->author_id === Auth::id();
        $isWorkspaceAdmin = $comment->workspace->members()
            ->where('user_id', Auth::id())
            ->whereHas('role', function($query) {
                $query->whereIn('key_name', ['workspace_owner', 'workspace_admin']);
            })
            ->exists();

        if (!$isAuthor && !$isWorkspaceAdmin) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Soft delete the comment and its replies
        $comment->replies()->update(['deleted_at' => now()]);
        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully']);
    }

    /**
     * Add a reaction to a comment.
     */
    public function addReaction(Request $request, $commentId)
    {
        $comment = Comment::findOrFail($commentId);

        // Check if user has access to the workspace
        if (!$comment->workspace->members()->where('user_id', Auth::id())->exists()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'reaction' => 'required|string|max:20'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Using the comment_reactions table (not shown in schema but implied)
            DB::table('comment_reactions')->updateOrInsert(
                [
                    'comment_id' => $commentId,
                    'user_id' => Auth::id(),
                    'reaction' => $request->reaction
                ],
                ['created_at' => now(), 'updated_at' => now()]
            );

            return response()->json(['message' => 'Reaction added successfully']);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to add reaction'], 500);
        }
    }

    /**
     * Remove a reaction from a comment.
     */
    public function removeReaction(Request $request, $commentId)
    {
        $validator = Validator::make($request->all(), [
            'reaction' => 'required|string|max:20'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::table('comment_reactions')
            ->where('comment_id', $commentId)
            ->where('user_id', Auth::id())
            ->where('reaction', $request->reaction)
            ->delete();

        return response()->json(['message' => 'Reaction removed successfully']);
    }

    /**
     * Check if user has permission to comment on a resource.
     */
    private function hasCommentPermission($member, $resourceType, $resourceId)
    {
        // Simplified permission check - in reality, you'd check ACLs
        $role = $member->role;
        
        return $role->permissions()->where('key_name', 'comment.create')->exists();
    }

    /**
     * Handle user mentions in comments.
     */
    private function handleMentions($comment, $body)
    {
        preg_match_all('/@([a-zA-Z0-9_]+)/', $body, $matches);
        
        if (!empty($matches[1])) {
            $usernames = $matches[1];
            $users = User::whereIn('username', $usernames)->get();
            
            foreach ($users as $user) {
                // Create notification for mentioned user
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'mention',
                    'payload' => json_encode([
                        'comment_id' => $comment->id,
                        'author_id' => $comment->author_id,
                        'resource_type' => $comment->resource_type,
                        'resource_id' => $comment->resource_id
                    ]),
                    'created_at' => now()
                ]);
            }
        }
    }
}