<?php

namespace App\Http\Controllers;

use App\Models\{
    Document, DocumentVersion, Folder, Workspace, 
    SharedLink, Favorite, RetentionPolicy, LegalHold,
    Comment, Approval, User, WorkspaceMember
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{
    Storage, Auth, DB, Validator
};
use Illuminate\Support\Str;
use Carbon\Carbon;

class FilesController extends Controller
{
    // Core CRUD & Actions
    
    public function index($workspaceId, $folderId = null)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $folder = $folderId ? Folder::findOrFail($folderId) : null;
        
        $documents = Document::where('workspace_id', $workspaceId)
            ->where('folder_id', $folderId)
            ->where('is_deleted', false)
            ->with(['uploadedBy', 'folder'])
            ->orderBy('created_at', 'desc')
            ->paginate(25);
            
        $folders = Folder::where('workspace_id', $workspaceId)
            ->where('parent_id', $folderId)
            ->where('is_deleted', false)
            ->orderBy('name')
            ->get();
            
        return view('files.index', compact('workspace', 'folder', 'documents', 'folders'));
    }

    public function browse($workspaceId, $folderId = null)
    {
        // Similar to index but optimized for HTMX responses
        $workspace = Workspace::findOrFail($workspaceId);
        $folder = $folderId ? Folder::findOrFail($folderId) : null;
        
        $documents = Document::where('workspace_id', $workspaceId)
            ->where('folder_id', $folderId)
            ->where('is_deleted', false)
            ->with(['uploadedBy'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        $folders = Folder::where('workspace_id', $workspaceId)
            ->where('parent_id', $folderId)
            ->where('is_deleted', false)
            ->orderBy('name')
            ->get();
            
        return view('files.partials.file-list', compact('workspace', 'folder', 'documents', 'folders'));
    }

    public function show($fileId)
    {
        $document = Document::with([
            'versions', 
            'folder', 
            'workspace', 
            'uploadedBy',
            'comments' => function($q) {
                $q->where('resolved_at', null)->with('author');
            }
        ])->findOrFail($fileId);
        
        // Check permissions
        $this->authorize('view', $document);
        
        $relatedDocuments = Document::where('workspace_id', $document->workspace_id)
            ->where('folder_id', $document->folder_id)
            ->where('id', '!=', $document->id)
            ->where('is_deleted', false)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
            
        return view('files.show', compact('document', 'relatedDocuments'));
    }

    public function delete($fileId)
    {
        $document = Document::findOrFail($fileId);
        $this->authorize('delete', $document);
        
        DB::transaction(function() use ($document) {
            // Soft delete
            $document->update([
                'is_deleted' => true,
                'deleted_at' => now()
            ]);
            
            // Log the deletion
            AuditLog::create([
                'workspace_id' => $document->workspace_id,
                'actor_user_id' => Auth::id(),
                'action' => 'document.delete',
                'resource_type' => 'document',
                'resource_id' => $document->id,
                'metadata' => json_encode(['name' => $document->original_name])
            ]);
        });
        
        return redirect()->route('files.index', $document->workspace_id)
            ->with('success', 'File moved to trash');
    }

    public function showAuditTrail($fileId)
    {
        $document = Document::findOrFail($fileId);
        $this->authorize('view', $document);
        
        $auditLogs = AuditLog::where('resource_type', 'document')
            ->where('resource_id', $fileId)
            ->with('actor')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        return view('files.audit-trail', compact('document', 'auditLogs'));
    }

    public function copy($fileId)
    {
        $original = Document::with('versions')->findOrFail($fileId);
        $this->authorize('view', $original);
        
        $newDocument = DB::transaction(function() use ($original) {
            // Create copy of document
            $copy = $original->replicate();
            $copy->original_name = 'Copy of ' . $original->original_name;
            $copy->uuid_bin = Str::orderedUuid()->getBytes();
            $copy->save();
            
            // Copy versions
            foreach ($original->versions as $version) {
                $versionCopy = $version->replicate();
                $versionCopy->document_id = $copy->id;
                $versionCopy->uuid_bin = Str::orderedUuid()->getBytes();
                $versionCopy->save();
                
                // Copy the actual file
                Storage::copy($version->storage_key, str_replace(
                    $original->id, $copy->id, $version->storage_key
                ));
            }
            
            return $copy;
        });
        
        return redirect()->route('files.show', $newDocument->id)
            ->with('success', 'File copied successfully');
    }

    public function download($fileId)
    {
        $document = Document::findOrFail($fileId);
        $this->authorize('download', $document);
        
        $version = $document->versions()->orderBy('version_no', 'desc')->first();
        
        if (!$version) {
            abort(404, 'No file version found');
        }
        
        // Log download
        DownloadHistory::create([
            'user_id' => Auth::id(),
            'document_version_id' => $version->id,
            'ip_address' => inet_pton(request()->ip()),
            'user_agent' => request()->userAgent()
        ]);
        
        return Storage::download($version->storage_key, $document->original_name);
    }

    public function lock($fileId)
    {
        $document = Document::findOrFail($fileId);
        $this->authorize('edit', $document);
        
        $document->update([
            'is_locked' => true,
            'locked_by' => Auth::id(),
            'locked_at' => now()
        ]);
        
        return back()->with('success', 'File locked successfully');
    }

    public function move($fileId)
    {
        $document = Document::findOrFail($fileId);
        $this->authorize('edit', $document);
        
        $validated = request()->validate([
            'folder_id' => 'nullable|exists:folders,id',
            'workspace_id' => 'sometimes|exists:workspaces,id'
        ]);
        
        $document->update($validated);
        
        return back()->with('success', 'File moved successfully');
    }

    public function preview($fileId)
    {
        $document = Document::findOrFail($fileId);
        $this->authorize('view', $document);
        
        $preview = $document->previews()->where('kind', 'pdf')->first();
        
        if (!$preview) {
            abort(404, 'Preview not available');
        }
        
        return response()->file(Storage::path($preview->storage_key));
    }

    public function rename($fileId)
    {
        $document = Document::findOrFail($fileId);
        $this->authorize('edit', $document);
        
        $validated = request()->validate([
            'name' => 'required|string|max:255'
        ]);
        
        $document->update(['original_name' => $validated['name']]);
        
        return back()->with('success', 'File renamed successfully');
    }

    public function unlock($fileId)
    {
        $document = Document::findOrFail($fileId);
        $this->authorize('edit', $document);
        
        // Only allow unlock by locker or admin
        if ($document->locked_by !== Auth::id() && !Auth::user()->hasRole('system_admin')) {
            abort(403, 'You can only unlock files you locked');
        }
        
        $document->update([
            'is_locked' => false,
            'locked_by' => null,
            'locked_at' => null
        ]);
        
        return back()->with('success', 'File unlocked successfully');
    }

    public function createFolder($workspaceId, $parentFolderId = null)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $parentFolder = $parentFolderId ? Folder::findOrFail($parentFolderId) : null;
        
        $this->authorize('create', [Folder::class, $workspace]);
        
        $validated = request()->validate([
            'name' => 'required|string|max:255'
        ]);
        
        $folder = Folder::create([
            'name' => $validated['name'],
            'parent_id' => $parentFolderId,
            'workspace_id' => $workspaceId,
            'path' => $parentFolder ? $parentFolder->path . $parentFolder->id . '/' : '/',
            'depth' => $parentFolder ? $parentFolder->depth + 1 : 0,
            'created_by' => Auth::id()
        ]);
        
        return redirect()->route('files.browse', [$workspaceId, $parentFolderId])
            ->with('success', 'Folder created successfully');
    }

    public function upload($workspaceId, $folderId = null)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $folder = $folderId ? Folder::findOrFail($folderId) : null;
        
        $this->authorize('upload', [Document::class, $workspace]);
        
        $validated = request()->validate([
            'file' => 'required|file|max:' . ($workspace->storage_quota - $workspace->storage_used),
            'description' => 'nullable|string'
        ]);
        
        $file = request()->file('file');
        $fileSize = $file->getSize();
        
        DB::transaction(function() use ($workspace, $folder, $file, $fileSize, $validated) {
            // Create document record
            $document = Document::create([
                'uuid_bin' => Str::orderedUuid()->getBytes(),
                'original_name' => $file->getClientOriginalName(),
                'folder_id' => $folder?->id,
                'workspace_id' => $workspace->id,
                'uploaded_by' => Auth::id(),
                'size_bytes' => $fileSize,
                'mime_type' => $file->getMimeType(),
                'checksum' => hash_file('sha256', $file->getRealPath()),
                'description' => $validated['description'] ?? null
            ]);
            
            // Store file
            $storagePath = "workspaces/{$workspace->id}/documents/{$document->id}/" . Str::uuid();
            Storage::put($storagePath, file_get_contents($file->getRealPath()));
            
            // Create version
            DocumentVersion::create([
                'uuid_bin' => Str::orderedUuid()->getBytes(),
                'document_id' => $document->id,
                'version_no' => 1,
                'storage_key' => $storagePath,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension(),
                'size_bytes' => $fileSize,
                'sha256' => hash_file('sha256', $file->getRealPath()),
                'uploaded_by' => Auth::id()
            ]);
            
            // Update workspace storage
            $workspace->increment('storage_used', $fileSize);
        });
        
        return redirect()->route('files.browse', [$workspaceId, $folderId])
            ->with('success', 'File uploaded successfully');
    }

    // Sharing & Collaboration

    public function share($fileId)
    {
        $document = Document::findOrFail($fileId);
        $this->authorize('share', $document);
        
        $validated = request()->validate([
            'expires_at' => 'nullable|date',
            'max_downloads' => 'nullable|integer|min:1',
            'password' => 'nullable|string|min:6'
        ]);
        
        $share = SharedLink::create([
            'workspace_id' => $document->workspace_id,
            'resource_type' => 'document',
            'resource_id' => $document->id,
            'token' => Str::random(32),
            'expires_at' => $validated['expires_at'] ?? null,
            'max_downloads' => $validated['max_downloads'] ?? null,
            'password_hash' => $validated['password'] ? bcrypt($validated['password']) : null,
            'created_by' => Auth::id()
        ]);
        
        return response()->json([
            'share_url' => route('shared.show', $share->token),
            'expires_at' => $share->expires_at
        ]);
    }

    public function updateShare($shareId)
    {
        $share = SharedLink::findOrFail($shareId);
        $this->authorize('update', $share);
        
        $validated = request()->validate([
            'expires_at' => 'nullable|date',
            'max_downloads' => 'nullable|integer|min:1'
        ]);
        
        $share->update($validated);
        
        return back()->with('success', 'Share settings updated');
    }

    public function revokeShare($shareId)
    {
        $share = SharedLink::findOrFail($shareId);
        $this->authorize('delete', $share);
        
        $share->delete();
        
        return back()->with('success', 'Share revoked successfully');
    }

    // Version Control

    public function getVersions($fileId)
    {
        $document = Document::findOrFail($fileId);
        $this->authorize('view', $document);
        
        $versions = $document->versions()->with('uploadedBy')->orderBy('version_no', 'desc')->get();
        
        return view('files.versions', compact('document', 'versions'));
    }

    public function compareVersions($fileId)
    {
        $document = Document::findOrFail($fileId);
        $this->authorize('view', $document);
        
        $validated = request()->validate([
            'version1' => 'required|exists:document_versions,id',
            'version2' => 'required|exists:document_versions,id'
        ]);
        
        $version1 = DocumentVersion::find($validated['version1']);
        $version2 = DocumentVersion::find($validated['version2']);
        
        // Implement comparison logic based on file type
        // This would typically involve text extraction and diff generation
        
        return view('files.compare', compact('document', 'version1', 'version2'));
    }

    public function restoreVersion($fileId, $versionId)
    {
        $document = Document::findOrFail($fileId);
        $version = DocumentVersion::findOrFail($versionId);
        
        $this->authorize('edit', $document);
        
        DB::transaction(function() use ($document, $version) {
            // Create new version from the restored one
            $newVersion = $version->replicate();
            $newVersion->version_no = $document->latest_version + 1;
            $newVersion->change_note = "Restored from version {$version->version_no}";
            $newVersion->save();
            
            $document->update(['latest_version' => $newVersion->version_no]);
        });
        
        return back()->with('success', 'Version restored successfully');
    }

    // Security & Protection

    public function setPassword($fileId)
    {
        $document = Document::findOrFail($fileId);
        $this->authorize('edit', $document);
        
        $validated = request()->validate([
            'password' => 'required|string|min:6'
        ]);
        
        $document->update(['password_hash' => bcrypt($validated['password'])]);
        
        return back()->with('success', 'Password protection enabled');
    }

    public function remoteWipe($fileId)
    {
        $document = Document::findOrFail($fileId);
        $this->authorize('delete', $document);
        
        DB::transaction(function() use ($document) {
            // Mark for remote wipe
            $document->update(['is_remote_wiped' => true]);
            
            // Actually delete files from storage (would be handled by a queue job)
            foreach ($document->versions as $version) {
                Storage::delete($version->storage_key);
            }
        });
        
        return response()->json(['message' => 'Remote wipe initiated']);
    }

    // Bookmarks

    public function addBookmark($fileId)
    {
        $document = Document::findOrFail($fileId);
        $this->authorize('view', $document);
        
        Favorite::firstOrCreate([
            'user_id' => Auth::id(),
            'resource_type' => 'document',
            'resource_id' => $document->id
        ]);
        
        return response()->json(['bookmarked' => true]);
    }

    public function removeBookmark($fileId)
    {
        Favorite::where('user_id', Auth::id())
            ->where('resource_type', 'document')
            ->where('resource_id', $fileId)
            ->delete();
            
        return response()->json(['bookmarked' => false]);
    }

    public function getBookmarks()
    {
        $bookmarks = Favorite::with(['resource' => function($query) {
            $query->where('is_deleted', false);
        }])
        ->where('user_id', Auth::id())
        ->where('resource_type', 'document')
        ->orderBy('created_at', 'desc')
        ->paginate(20);
        
        return view('files.bookmarks', compact('bookmarks'));
    }

    // Advanced Security & Governance

    public function applyClassification($fileId)
    {
        $document = Document::findOrFail($fileId);
        $this->authorize('edit', $document);
        
        $validated = request()->validate([
            'classification' => 'required|in:public,internal,confidential,restricted'
        ]);
        
        $document->update(['confidentiality' => $validated['classification']]);
        
        return back()->with('success', 'Classification applied');
    }

    public function getClassificationTemplates()
    {
        $templates = [
            'public' => ['label' => 'Public', 'description' => 'Available to anyone'],
            'internal' => ['label' => 'Internal', 'description' => 'Company internal use only'],
            'confidential' => ['label' => 'Confidential', 'description' => 'Limited distribution'],
            'restricted' => ['label' => 'Restricted', 'description' => 'Strictly controlled access']
        ];
        
        return response()->json($templates);
    }

    public function watermarkPreview($fileId)
    {
        $document = Document::findOrFail($fileId);
        $this->authorize('view', $document);
        
        // Generate watermarked preview (implementation depends on file type)
        // This would typically use a queue job
        
        return response()->json(['preview_url' => route('files.preview', $fileId) . '?watermark=true']);
    }

    public function setWatermarkPolicy($fileId)
    {
        $document = Document::findOrFail($fileId);
        $this->authorize('edit', $document);
        
        $validated = request()->validate([
            'watermark_text' => 'nullable|string',
            'watermark_position' => 'nullable|in:top-left,top-right,bottom-left,bottom-right,center'
        ]);
        
        // Store watermark settings (would typically be in a separate table)
        $document->settings()->updateOrCreate(
            ['key' => 'watermark'],
            ['value' => json_encode($validated)]
        );
        
        return back()->with('success', 'Watermark policy updated');
    }

    public function applyRetentionPolicy($fileId)
    {
        $document = Document::findOrFail($fileId);
        $this->authorize('edit', $document);
        
        $validated = request()->validate([
            'policy_id' => 'required|exists:retention_policies,id'
        ]);
        
        $policy = RetentionPolicy::find($validated['policy_id']);
        
        DocumentRetention::updateOrCreate(
            ['document_id' => $document->id],
            [
                'policy_id' => $policy->id,
                'applied_by' => Auth::id(),
                'expires_at' => now()->add($policy->keep_rule_json->keep_months, 'months')
            ]
        );
        
        return back()->with('success', 'Retention policy applied');
    }

    public function placeLegalHold($fileId)
    {
        $document = Document::findOrFail($fileId);
        $this->authorize('administer', $document);
        
        $validated = request()->validate([
            'legal_hold_id' => 'required|exists:legal_holds,id',
            'reason' => 'required|string'
        ]);
        
        LegalHoldItem::create([
            'legal_hold_id' => $validated['legal_hold_id'],
            'resource_type' => 'document',
            'resource_id' => $document->id,
            'placed_by' => Auth::id()
        ]);
        
        return back()->with('success', 'Legal hold placed on document');
    }

    public function releaseLegalHold($fileId)
    {
        $document = Document::findOrFail($fileId);
        $this->authorize('administer', $document);
        
        LegalHoldItem::where('resource_type', 'document')
            ->where('resource_id', $document->id)
            ->update(['released_at' => now(), 'released_by' => Auth::id()]);
            
        return back()->with('success', 'Legal hold released');
    }

    public function getFileRelationships($fileId)
    {
        $document = Document::findOrFail($fileId);
        $this->authorize('view', $document);
        
        $relationships = [
            'linked_documents' => $document->linkedDocuments(),
            'references' => $document->referencingDocuments(),
            'related_comments' => $document->comments()->with('author')->get()
        ];
        
        return response()->json($relationships);
    }

    public function createFileRelationship($fileId)
    {
        $document = Document::findOrFail($fileId);
        $this->authorize('edit', $document);
        
        $validated = request()->validate([
            'related_file_id' => 'required|exists:documents,id',
            'relationship_type' => 'required|in:reference,attachment,version,related'
        ]);
        
        // Implementation would depend on your relationship structure
        DocumentRelationship::create([
            'source_document_id' => $document->id,
            'target_document_id' => $validated['related_file_id'],
            'relationship_type' => $validated['relationship_type'],
            'created_by' => Auth::id()
        ]);
        
        return back()->with('success', 'Relationship created');
    }

    public function bulkUpdateMetadata()
    {
        $validated = request()->validate([
            'file_ids' => 'required|array',
            'file_ids.*' => 'exists:documents,id',
            'metadata' => 'required|array'
        ]);
        
        Document::whereIn('id', $validated['file_ids'])
            ->update(['metadata' => DB::raw("JSON_MERGE_PATCH(metadata, '".json_encode($validated['metadata'])."')")]);
            
        return response()->json(['updated' => count($validated['file_ids'])]);
    }

    // Mobile & Offline

    public function markForOffline($fileId)
    {
        $document = Document::findOrFail($fileId);
        $this->authorize('view', $document);
        
        // Mark document for offline access
        UserOfflineDocument::firstOrCreate([
            'user_id' => Auth::id(),
            'document_id' => $document->id
        ]);
        
        return response()->json(['offline' => true]);
    }

    public function getOfflineContent()
    {
        $offlineDocuments = UserOfflineDocument::with('document')
            ->where('user_id', Auth::id())
            ->get()
            ->pluck('document');
            
        return response()->json($offlineDocuments);
    }

    public function syncOfflineChanges()
    {
        // This would handle syncing changes made while offline
        // Implementation depends on your sync strategy
        
        return response()->json(['synced' => true]);
    }

    public function getMobilePreview($fileId)
    {
        $document = Document::findOrFail($fileId);
        $this->authorize('view', $document);
        
        // Generate mobile-optimized preview
        $preview = $document->previews()->where('kind', 'thumb')->first();
        
        if (!$preview) {
            abort(404, 'Mobile preview not available');
        }
        
        return response()->file(Storage::path($preview->storage_key));
    }

    // HTMX Fragments & Cards

    public function overview()
    {
        $workspaceId = request('workspace_id');
        $stats = [
            'total_files' => Document::where('workspace_id', $workspaceId)->count(),
            'total_size' => Document::where('workspace_id', $workspaceId)->sum('size_bytes'),
            'recent_files' => Document::where('workspace_id', $workspaceId)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
        ];
        
        return view('files.partials.overview-card', compact('stats'));
    }

    public function pinned($workspaceId)
    {
        $pinnedFiles = Favorite::with('resource')
            ->where('user_id', Auth::id())
            ->whereHas('resource', function($q) use ($workspaceId) {
                $q->where('workspace_id', $workspaceId);
            })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
            
        return view('files.partials.pinned-files', compact('pinnedFiles'));
    }

    public function toolbar($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $currentFolder = request('folder_id') ? Folder::find(request('folder_id')) : null;
        
        return view('files.partials.toolbar', compact('workspace', 'currentFolder'));
    }

    public function breadcrumbs($workspaceId, $folderId = null)
    {
        $breadcrumbs = [];
        $currentFolder = $folderId ? Folder::find($folderId) : null;
        
        if ($currentFolder) {
            $folder = $currentFolder;
            while ($folder) {
                $breadcrumbs[] = $folder;
                $folder = $folder->parent;
            }
        }
        
        $breadcrumbs = array_reverse($breadcrumbs);
        
        return view('files.partials.breadcrumbs', compact('breadcrumbs'));
    }

    public function view($workspaceId, $folderId = null, $mode = 'list')
    {
        $documents = Document::where('workspace_id', $workspaceId)
            ->where('folder_id', $folderId)
            ->where('is_deleted', false)
            ->with('uploadedBy')
            ->orderBy('created_at', 'desc')
            ->paginate($mode === 'grid' ? 12 : 20);
            
        return view('files.partials.' . $mode . '-view', compact('documents'));
    }

    public function searchLocal($workspaceId, $folderId = null)
    {
        $query = request('q');
        
        $documents = Document::where('workspace_id', $workspaceId)
            ->where('folder_id', $folderId)
            ->where('is_deleted', false)
            ->where(function($q) use ($query) {
                $q->where('original_name', 'LIKE', "%{$query}%")
                  ->orWhere('description', 'LIKE', "%{$query}%");
            })
            ->with('uploadedBy')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
            
        return view('files.partials.search-results', compact('documents'));
    }

    public function recentDropdown()
    {
        $recentFiles = Document::where('workspace_id', request('workspace_id'))
            ->where('is_deleted', false)
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();
            
        return view('files.partials.recent-dropdown', compact('recentFiles'));
    }

    public function bookmarksDropdown()
    {
        $bookmarks = Favorite::with('resource')
            ->where('user_id', Auth::id())
            ->where('resource_type', 'document')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
            
        return view('files.partials.bookmarks-dropdown', compact('bookmarks'));
    }

    public function approvals()
    {
        $pendingApprovals = Approval::with(['documentVersion.document', 'assignedTo'])
            ->where('assigned_to', Auth::id())
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return view('files.partials.approvals-list', compact('pendingApprovals'));
    }

    public function awaitingApprovalCard()
    {
        $approvalCount = Approval::where('assigned_to', Auth::id())
            ->where('status', 'pending')
            ->count();
            
        return view('files.partials.awaiting-approval-card', compact('approvalCount'));
    }

    public function locked()
    {
        $lockedFiles = Document::where('is_locked', true)
            ->where('workspace_id', request('workspace_id'))
            ->with('lockedBy')
            ->orderBy('locked_at', 'desc')
            ->get();
            
        return view('files.partials.locked-files', compact('lockedFiles'));
    }

    public function lockedFilesCard()
    {
        $lockedCount = Document::where('is_locked', true)
            ->where('workspace_id', request('workspace_id'))
            ->count();
            
        return view('files.partials.locked-files-card', compact('lockedCount'));
    }
    /**
 * Web index for files with HTMX support.
 */
public function webIndex()
{
    $files = Document::with(['folder', 'workspace', 'uploadedBy'])
        ->where('workspace_id', auth()->user()->currentWorkspace->id)
        ->where('is_deleted', false)
        ->orderBy('created_at', 'desc')
        ->paginate(24);

    if (request()->wantsJson() || request()->hasHeader('HX-Request')) {
        return response()->json($files);
    }

    return view('files.index', compact('files'));
}

/**
 * Web show for files with HTMX support.
 */
public function webShow($id)
{
    $file = Document::with(['versions', 'folder', 'workspace', 'uploadedBy'])
        ->where('workspace_id', auth()->user()->currentWorkspace->id)
        ->findOrFail($id);

    if (request()->wantsJson() || request()->hasHeader('HX-Request')) {
        return response()->json($file);
    }

    return view('files.show', compact('file'));
}

/**
 * Web create form for files.
 */
public function webCreate()
{
    $folders = Folder::where('workspace_id', auth()->user()->currentWorkspace->id)
        ->where('is_deleted', false)
        ->get();

    if (request()->hasHeader('HX-Request')) {
        return view('files.partials.create-form', compact('folders'));
    }

    return view('files.create', compact('folders'));
}

/**
 * Web edit form for files.
 */
public function webEdit($id)
{
    $file = Document::where('workspace_id', auth()->user()->currentWorkspace->id)
        ->findOrFail($id);
    
    $folders = Folder::where('workspace_id', auth()->user()->currentWorkspace->id)
        ->where('is_deleted', false)
        ->get();

    if (request()->hasHeader('HX-Request')) {
        return view('files.partials.edit-form', compact('file', 'folders'));
    }

    return view('files.edit', compact('file', 'folders'));

}
// Add these methods to your existing FilesController

/**
 * Show the upload form
 */
public function showUploadForm($workspaceId, $folderId = null)
{
    $workspace = Workspace::findOrFail($workspaceId);
    $folder = $folderId ? Folder::find($folderId) : null;
    
    $this->authorize('upload', [Document::class, $workspace]);
    
    return view('files.partials.upload-form', compact('workspace', 'folder'));
}

/**
 * Handle multiple file uploads
 */
public function uploadMultiple(Request $request, $workspaceId, $folderId = null)
{
    $workspace = Workspace::findOrFail($workspaceId);
    $folder = $folderId ? Folder::find($folderId) : null;
    
    $this->authorize('upload', [Document::class, $workspace]);
    
    $request->validate([
        'files.*' => 'required|file|max:' . (1024 * 1024), // 1GB max per file
        'description' => 'nullable|string'
    ]);
    
    $uploadedFiles = [];
    
    DB::beginTransaction();
    try {
        foreach ($request->file('files') as $file) {
            $fileSize = $file->getSize();
            
            // Check workspace storage quota
            if (($workspace->storage_used + $fileSize) > $workspace->storage_quota) {
                throw new \Exception('Storage quota exceeded');
            }
            
            // Create document record
            $document = Document::create([
                'uuid_bin' => Str::orderedUuid()->getBytes(),
                'original_name' => $file->getClientOriginalName(),
                'folder_id' => $folder?->id,
                'workspace_id' => $workspace->id,
                'uploaded_by' => Auth::id(),
                'size_bytes' => $fileSize,
                'mime_type' => $file->getMimeType(),
                'checksum' => hash_file('sha256', $file->getRealPath()),
                'description' => $request->description
            ]);
            
            // Store file
            $storagePath = "workspaces/{$workspace->id}/documents/{$document->id}/" . Str::uuid();
            Storage::put($storagePath, file_get_contents($file->getRealPath()));
            
            // Create version
            DocumentVersion::create([
                'uuid_bin' => Str::orderedUuid()->getBytes(),
                'document_id' => $document->id,
                'version_no' => 1,
                'storage_key' => $storagePath,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension(),
                'size_bytes' => $fileSize,
                'sha256' => hash_file('sha256', $file->getRealPath()),
                'uploaded_by' => Auth::id()
            ]);
            
            // Update workspace storage
            $workspace->increment('storage_used', $fileSize);
            
            $uploadedFiles[] = $document;
        }
        
        DB::commit();
        
        if ($request->wantsJson() || $request->hasHeader('HX-Request')) {
            return response()->json([
                'success' => true,
                'message' => count($uploadedFiles) . ' files uploaded successfully',
                'files' => $uploadedFiles
            ]);
        }
        
        return redirect()->back()
            ->with('success', count($uploadedFiles) . ' files uploaded successfully');
            
    } catch (\Exception $e) {
        DB::rollBack();
        
        if ($request->wantsJson() || $request->hasHeader('HX-Request')) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 422);
        }
        
        return redirect()->back()
            ->with('error', 'Upload failed: ' . $e->getMessage());
    }
}

/**
 * Web edit form for files
 */
public function webEdit($id)
{
    $document = Document::where('workspace_id', auth()->user()->currentWorkspace->id)
        ->findOrFail($id);
    
    $folders = Folder::where('workspace_id', auth()->user()->currentWorkspace->id)
        ->where('is_deleted', false)
        ->get();

    if (request()->hasHeader('HX-Request')) {
        return view('files.partials.edit-form', compact('document', 'folders'));
    }

    return view('files.edit', compact('document', 'folders'));
}

/**
 * Web update for files
 */
public function webUpdate(Request $request, $id)
{
    $document = Document::where('workspace_id', auth()->user()->currentWorkspace->id)
        ->findOrFail($id);
    
    $this->authorize('edit', $document);
    
    $validated = $request->validate([
        'original_name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'folder_id' => 'nullable|exists:folders,id'
    ]);
    
    $document->update($validated);
    
    if ($request->wantsJson() || $request->hasHeader('HX-Request')) {
        return response()->json([
            'success' => true,
            'message' => 'File updated successfully',
            'document' => $document
        ]);
    }
    
    return redirect()->route('files.show', $document->id)
        ->with('success', 'File updated successfully');
}
}