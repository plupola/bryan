<?php

use Illuminate\Support\Facades\Route;

// Controllers (adjust namespaces if you keep them outside App\Http\Controllers)
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WorkspacesController;
use App\Http\Controllers\FilesController;
use App\Http\Controllers\FileRequestsController;
use App\Http\Controllers\CommentsController;
use App\Http\Controllers\TasksController;
use App\Http\Controllers\TeamsController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CompaniesController;

/*
|--------------------------------------------------------------------------
| Public / Auth
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('register',        [AuthController::class, 'register'])->name('auth.register');
    Route::post('login',           [AuthController::class, 'login'])->name('auth.login');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
    Route::post('reset-password',  [AuthController::class, 'resetPassword'])->name('auth.reset-password');
});

// External/public upload for a File Request (token validated inside controller)
Route::post('filerequests/{id}/upload', [FileRequestsController::class, 'upload'])
    ->middleware(['file.upload', 'validate:fr.publicUpload'])
    ->name('filerequests.upload');

/*
|--------------------------------------------------------------------------
| Everything below requires at least optional or full auth
|--------------------------------------------------------------------------
*/

// 2FA + logout + change password (authenticated)
Route::middleware(['auth.jwt'])->prefix('auth')->group(function () {
    Route::post('logout',          [AuthController::class, 'logout'])->name('auth.logout');
    Route::post('change-password', [AuthController::class, 'changePassword'])->name('auth.change-password');
    Route::post('2fa/verify',      [AuthController::class, 'twoFactorVerify'])->name('auth.2fa.verify');
    Route::post('2fa/setup',       [AuthController::class, 'twoFactorSetup'])->name('auth.2fa.setup');
    Route::post('2fa/disable',     [AuthController::class, 'twoFactorDisable'])->name('auth.2fa.disable');
});

/*
|--------------------------------------------------------------------------
| Workspaces
|--------------------------------------------------------------------------
*/
Route::middleware(['auth.jwt'])->group(function () {
    Route::get('workspaces',          [WorkspacesController::class, 'index'])->name('workspaces.index');
    Route::post('workspaces',         [WorkspacesController::class, 'create'])
        ->middleware(['permission:workspace.create','validate:ws.create'])
        ->name('workspaces.create');

    Route::prefix('workspaces/{workspace}')
        ->middleware(['load.workspace'])
        ->group(function () {
            Route::get('',            [WorkspacesController::class, 'show'])->name('workspaces.show');
            Route::put('',            [WorkspacesController::class, 'update'])->middleware(['permission:workspace.manage','validate:ws.update'])->name('workspaces.update');
            Route::delete('',         [WorkspacesController::class, 'delete'])->middleware(['workspace.owner','permission:workspace.delete'])->name('workspaces.delete');

            // Members
            Route::get('users',       [WorkspacesController::class, 'listUsers'])->middleware('permission:workspace.manage')->name('workspaces.users.index');
            Route::post('invite',     [WorkspacesController::class, 'inviteUser'])->middleware(['workspace.admin','validate:ws.invite'])->name('workspaces.invite');
            Route::post('users',      [WorkspacesController::class, 'addUser'])->middleware(['workspace.admin','validate:ws.addUser'])->name('workspaces.users.add');
            Route::get('users/{user}',[WorkspacesController::class, 'getUserRole'])->middleware('workspace.admin')->name('workspaces.users.role');
            Route::put('users/{user}',[WorkspacesController::class, 'updateUserRole'])->middleware(['workspace.admin','validate:ws.updateUserRole'])->name('workspaces.users.update');
            Route::delete('users/{user}',[WorkspacesController::class, 'removeUser'])->middleware('workspace.admin')->name('workspaces.users.remove');

            // Settings / Stats
            Route::get('settings',    [WorkspacesController::class, 'getSettings'])->middleware('permission:workspace.manage')->name('workspaces.settings');
            Route::put('settings',    [WorkspacesController::class, 'updateSettings'])->middleware(['permission:workspace.manage','validate:ws.updateSettings'])->name('workspaces.settings.update');
            Route::get('stats',       [WorkspacesController::class, 'getStats'])->middleware('permission:workspace.manage')->name('workspaces.stats');

            // Workspace-scoped listing (root of tree)
            Route::get('files',       [FilesController::class, 'index'])->middleware('permission:document.view')->name('workspaces.files.index');

            // Teams (workspace level)
            Route::get('teams',       [TeamsController::class, 'index'])->middleware('permission:workspace.manage')->name('workspaces.teams.index');
            Route::post('teams',      [TeamsController::class, 'create'])->middleware(['permission:workspace.manage','validate:team.create'])->name('workspaces.teams.create');
        });

    // Teams (team id ops)
    Route::prefix('teams/{team}')->group(function () {
        Route::put('',            [TeamsController::class, 'update'])->middleware(['permission:workspace.manage','validate:team.update'])->name('teams.update');
        Route::delete('',         [TeamsController::class, 'delete'])->middleware('permission:workspace.manage')->name('teams.delete');
        Route::post('users',      [TeamsController::class, 'addUser'])->middleware(['permission:workspace.manage','validate:team.addUser'])->name('teams.users.add');
        Route::delete('users/{user}', [TeamsController::class, 'removeUser'])->middleware('permission:workspace.manage')->name('teams.users.remove');
        Route::post('sync',       [TeamsController::class, 'syncPermissions'])->middleware('permission:workspace.manage')->name('teams.permissions.sync');
    });
});

/*
|--------------------------------------------------------------------------
| Files & Folders
|--------------------------------------------------------------------------
*/
Route::middleware(['auth.jwt'])->group(function () {
    // Create folder (parent folder loaded via middleware if you use parent_id)
    Route::post('files/folder', [FilesController::class, 'createFolder'])
        ->middleware(['load.folder.optional','permission:folder.create','validate:file.createFolder'])
        ->name('files.folder.create');

    // Upload
    Route::post('files/upload', [FilesController::class, 'upload'])
        ->middleware(['permission:document.upload','check.quota','file.upload','validate:file.upload'])
        ->name('files.upload');

    // Bookmarks
    Route::get('files/bookmarks',      [FilesController::class, 'getBookmarks'])->name('files.bookmarks');
    Route::post('files/{document}/bookmark',   [FilesController::class, 'addBookmark'])->middleware('load.document')->name('files.bookmark.add');
    Route::delete('files/{document}/bookmark', [FilesController::class, 'removeBookmark'])->middleware('load.document')->name('files.bookmark.remove');

    // File/Folder by id
    Route::prefix('files/{document}')->middleware(['load.document'])->group(function () {
        Route::get('',               [FilesController::class, 'show'])->middleware('permission:document.view')->name('files.show');

        // NOTE: optional auth allows public-token downloads to work when no JWT present.
        Route::get('download',       [FilesController::class, 'download'])
            ->middleware(['optional.auth'])
            ->name('files.download'); // controller checks token or permissions

        Route::get('preview',        [FilesController::class, 'preview'])->middleware('permission:document.view')->name('files.preview');

        Route::patch('rename',       [FilesController::class, 'rename'])->middleware(['permission:document.edit','validate:file.rename'])->name('files.rename');
        Route::patch('move',         [FilesController::class, 'move'])->middleware(['permission:document.edit','validate:file.move'])->name('files.move');
        Route::post('copy',          [FilesController::class, 'copy'])->middleware(['permission:document.edit','validate:file.copy'])->name('files.copy');
        Route::delete('',            [FilesController::class, 'delete'])->middleware('permission:document.delete')->name('files.delete');

        // Versioning
        Route::get('versions',                     [FilesController::class, 'getVersions'])->middleware('permission:version.manage')->name('files.versions');
        Route::post('versions/{version}/restore', [FilesController::class, 'restoreVersion'])->middleware(['permission:version.rollback','validate:file.restoreVersion'])->name('files.versions.restore');
        Route::get('compare',                      [FilesController::class, 'compareVersions'])->middleware('permission:version.manage')->name('files.versions.compare');

        // Locking
        Route::post('lock',   [FilesController::class, 'lock'])->middleware('permission:document.edit')->name('files.lock');
        Route::post('unlock', [FilesController::class, 'unlock'])->middleware('permission:document.edit')->name('files.unlock');

        // Sharing
        Route::post('share',   [FilesController::class, 'share'])->middleware(['permission:document.share','validate:file.share'])->name('files.share');
        Route::patch('share',  [FilesController::class, 'updateShare'])->middleware(['permission:document.share','validate:file.updateShare'])->name('files.share.update');
        Route::delete('share', [FilesController::class, 'revokeShare'])->middleware('permission:document.share')->name('files.share.revoke');
        Route::patch('password', [FilesController::class, 'setPassword'])->middleware(['permission:document.share','validate:file.setPassword'])->name('files.password.set');

        // Remote wipe & Audit
        Route::post('wipe',    [FilesController::class, 'remoteWipe'])->middleware(['permission:document.edit','validate:file.remoteWipe'])->name('files.wipe');
        Route::get('audit',    [FilesController::class, 'showAuditTrail'])->middleware('permission:audit.view')->name('files.audit');
    });
});

/*
|--------------------------------------------------------------------------
| File Downloads
|--------------------------------------------------------------------------
*/
Route::get('files/{document}/download', [FilesController::class, 'download'])
    ->name('files.download.external'); // Unique name for external file downloads

/*
|--------------------------------------------------------------------------
| Comments
|--------------------------------------------------------------------------
*/
Route::middleware(['auth.jwt'])->group(function () {
    Route::get('files/{document}/comments',  [CommentsController::class, 'index'])->middleware(['load.document','permission:document.view'])->name('files.comments.index');
    Route::post('files/{document}/comments', [CommentsController::class, 'create'])->middleware(['load.document','permission:comment.create','validate:comment.create'])->name('files.comments.create');

    Route::put('comments/{comment}',         [CommentsController::class, 'update'])->middleware(['load.comment','can.edit.comment','validate:comment.update'])->name('comments.update');
    Route::delete('comments/{comment}',      [CommentsController::class, 'delete'])->middleware(['load.comment','can.edit.comment'])->name('comments.delete');

    Route::post('comments/{comment}/reactions',   [CommentsController::class, 'addReaction'])->middleware(['load.comment','permission:comment.create','validate:comment.react'])->name('comments.reactions.add');
    Route::delete('comments/{comment}/reactions', [CommentsController::class, 'removeReaction'])->middleware(['load.comment','can.edit.comment'])->name('comments.reactions.remove');
});

/*
|--------------------------------------------------------------------------
| Tasks
|--------------------------------------------------------------------------
*/
Route::middleware(['auth.jwt'])->prefix('tasks')->group(function () {
    Route::get('',           [TasksController::class, 'index'])->middleware('validate:task.index')->name('tasks.index');
    Route::post('',          [TasksController::class, 'create'])->middleware('validate:task.create')->name('tasks.create');
    Route::get('{task}',     [TasksController::class, 'show'])->middleware('load.task')->name('tasks.show');
    Route::put('{task}',     [TasksController::class, 'update'])->middleware(['load.task','validate:task.update'])->name('tasks.update');
    Route::delete('{task}',  [TasksController::class, 'delete'])->middleware('load.task')->name('tasks.delete');
    Route::patch('{task}/status', [TasksController::class, 'updateStatus'])->middleware(['load.task','validate:task.status'])->name('tasks.status.update');
    Route::patch('{task}/assign', [TasksController::class, 'assign'])->middleware(['load.task','validate:task.assign'])->name('tasks.assign');
    Route::post('{task}/comments', [TasksController::class, 'addComment'])->middleware(['load.task','validate:task.comment'])->name('tasks.comments.add');
    Route::get('{task}/activity', [TasksController::class, 'getActivity'])->middleware('load.task')->name('tasks.activity');
});

/*
|--------------------------------------------------------------------------
| Users (directory)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth.jwt'])->group(function () {
    Route::get('users/search', [UsersController::class, 'search'])->middleware('validate:user.search')->name('users.search');
    Route::get('users/{user}', [UsersController::class, 'show'])->name('users.show');
    // NOTE: To avoid duplicate handlers, workspace user listing lives under WorkspacesController.
});

/*
|--------------------------------------------------------------------------
| Profile (self)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth.jwt'])->prefix('profile')->group(function () {
    Route::get('',                [ProfileController::class, 'show'])->name('profile.show');
    Route::put('',                [ProfileController::class, 'update'])->middleware('validate:profile.update')->name('profile.update');
    Route::put('password',        [ProfileController::class, 'updatePassword'])->middleware('validate:profile.password')->name('profile.password.update');
    Route::post('avatar',         [ProfileController::class, 'updateAvatar'])->middleware('file.upload')->name('profile.avatar.update');
    Route::get('sessions',        [ProfileController::class, 'getSessions'])->name('profile.sessions');
    Route::delete('sessions/{sid}', [ProfileController::class, 'invalidateSession'])->name('profile.sessions.invalidate');
});

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/
Route::middleware(['auth.jwt'])->prefix('notifications')->group(function () {
    Route::get('',                  [NotificationsController::class, 'index'])->name('notifications.index');
    Route::patch('{id}/read',       [NotificationsController::class, 'markAsRead'])->name('notifications.mark-as-read');
    Route::post('read-all',         [NotificationsController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
    Route::get('preferences',       [NotificationsController::class, 'getPreferences'])->name('notifications.preferences');
    Route::put('preferences',       [NotificationsController::class, 'updatePreferences'])->middleware('validate:notif.prefs')->name('notifications.preferences.update');
    Route::get('unread-count',      [NotificationsController::class, 'getUnreadCount'])->name('notifications.unread-count');
});

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/
Route::middleware(['auth.jwt','permission:search.query'])->get('search', [SearchController::class, 'global'])
    ->middleware('validate:search.global')
    ->name('search.global');

/*
|--------------------------------------------------------------------------
| Admin (system-wide)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth.jwt','system.admin'])->prefix('admin')->group(function () {
    Route::get('settings',            [AdminController::class, 'getSettings'])->name('admin.settings');
    Route::put('settings',            [AdminController::class, 'updateSettings'])->middleware('validate:admin.settings')->name('admin.settings.update');
    Route::put('security-settings',   [AdminController::class, 'updateSecuritySettings'])->middleware('validate:admin.security')->name('admin.security.update');

    Route::get('users',               [AdminController::class, 'listUsers'])->name('admin.users.index');
    Route::post('users',              [AdminController::class, 'createUser'])->middleware('validate:admin.createUser')->name('admin.users.create');
    Route::post('impersonate/{user}', [AdminController::class, 'impersonate'])->name('admin.impersonate');

    Route::get('workspace-requests',  [AdminController::class, 'getWorkspaceRequests'])->name('admin.workspace-requests.index');
    Route::post('workspace-requests/{id}/approve', [AdminController::class, 'approveWorkspaceRequest'])->name('admin.workspace-requests.approve');
    Route::post('workspace-requests/{id}/reject',  [AdminController::class, 'rejectWorkspaceRequest'])->name('admin.workspace-requests.reject');

    Route::get('workspaces',          [AdminController::class, 'indexWorkspaces'])->name('admin.workspaces.index');
    Route::post('workspaces/{workspace}/archive', [AdminController::class, 'archiveWorkspace'])->name('admin.workspaces.archive');

    Route::post('branding',           [AdminController::class, 'uploadBranding'])->middleware('file.upload')->name('admin.branding.upload');
});

/*
|--------------------------------------------------------------------------
| Companies
|--------------------------------------------------------------------------
*/
Route::middleware(['auth.jwt','system.admin'])->prefix('companies/{company}')->group(function () {
    Route::get('users',                    [CompaniesController::class, 'listUsers'])->name('companies.users.index');
    Route::post('users',                   [CompaniesController::class, 'addUser'])->middleware('validate:company.addUser')->name('companies.users.add');
    Route::delete('users/{user}',          [CompaniesController::class, 'removeUser'])->name('companies.users.remove');
    Route::get('public-files',             [CompaniesController::class, 'getPublicFiles'])->name('companies.public-files');
    Route::post('users/{user}/deactivate', [CompaniesController::class, 'deactivateUser'])->name('companies.users.deactivate');
});
