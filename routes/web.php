<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    AuthController,
    HomeController,
    FilesController,
    FileRequestsController,
    TasksController,
    TeamsController,
    AdminController,
    CompaniesController,
    NotificationsController,
    ProfileController,
    SearchController,
    WorkflowsController,
    WorkspacesController,
    ClientWorkspacesController,
    CommentsController,
    ReportsController,
    IntegrationsController
};

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|

|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'home'])->name('home');
Route::view('/welcome', 'welcome')->name('welcome'); // simple view preview

// Auth (guest-only) — controller-driven; no duplicate Route::view('/login', ...)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/register', [AuthController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.email');

    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});


/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    // Session / logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // App landing for signed-in users
    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');

    // Authentication management for logged-in users
    Route::get('/change-password', [AuthController::class, 'showChangePasswordForm'])->name('password.change');
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    Route::get('/two-factor', [AuthController::class, 'showTwoFactorForm'])->name('2fa.setup');
    Route::post('/two-factor/setup', [AuthController::class, 'twoFactorSetup'])->name('2fa.setup.store');
    Route::post('/two-factor/verify', [AuthController::class, 'twoFactorVerify'])->name('2fa.verify');
    Route::post('/two-factor/disable', [AuthController::class, 'twoFactorDisable'])->name('2fa.disable');

    // Profile
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show'])->name('profile.show');
        Route::put('/update', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
        Route::post('/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::get('/sessions', [ProfileController::class, 'getSessions'])->name('profile.sessions');
        Route::delete('/sessions/{sessionId}', [ProfileController::class, 'invalidateSession'])->name('profile.sessions.invalidate');
        Route::get('/menu', [ProfileController::class, 'menu'])->name('profile.menu');
    });
    
    // Notifications Routes
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationsController::class, 'index'])->name('notifications.index');
        Route::get('/preferences', [NotificationsController::class, 'getPreferences'])->name('notifications.preferences');
        Route::put('/preferences', [NotificationsController::class, 'updatePreferences'])->name('notifications.preferences.update');
        Route::post('/mark-all-read', [NotificationsController::class, 'markAllRead'])->name('notifications.mark-all-read');
        Route::get('/unread-count', [NotificationsController::class, 'getUnreadCount'])->name('notifications.unread-count');
        Route::post('/{notificationId}/read', [NotificationsController::class, 'markAsRead'])->name('notifications.mark-read');
        Route::get('/dropdown', [NotificationsController::class, 'dropdown'])->name('notifications.dropdown');
    });
    
    // Search Routes - FIXED: Moved outside any prefix to be accessible globally
    Route::prefix('search')->group(function () {
        Route::get('/global', [SearchController::class, 'global'])->name('search.global');
        Route::get('/activity-feed', [SearchController::class, 'activityFeed'])->name('search.activity.feed');
        Route::get('/workspace/{workspaceId}/activity', [SearchController::class, 'workspaceActivity'])->name('search.workspace.activity');
        Route::get('/quick', [SearchController::class, 'quick'])->name('search.quick');
        Route::get('/advanced', [SearchController::class, 'advancedSearch'])->name('search.advanced');
        Route::post('/save-query', [SearchController::class, 'saveSearchQuery'])->name('search.save.query');
        Route::get('/saved-searches', [SearchController::class, 'getSavedSearches'])->name('search.saved');
        Route::post('/search-within-results', [SearchController::class, 'searchWithinResults'])->name('search.within.results');
        Route::get('/analytics', [SearchController::class, 'getSearchAnalytics'])->name('search.analytics');
    });
    
    // Client Workspaces Routes
    Route::prefix('client-workspaces')->group(function () {
        Route::get('/', [ClientWorkspacesController::class, 'index'])->name('client-workspaces.index');
        Route::get('/{clientWorkspaceId}', [ClientWorkspacesController::class, 'show'])->name('client-workspaces.show');
        Route::put('/{clientWorkspaceId}/access-rules', [ClientWorkspacesController::class, 'updateAccessRules'])->name('client-workspaces.access-rules.update');
    });
    
    // Comments Routes
    Route::prefix('comments')->group(function () {
        Route::get('/', [CommentsController::class, 'index'])->name('comments.index');
        Route::post('/create', [CommentsController::class, 'create'])->name('comments.create');
        Route::put('/{commentId}', [CommentsController::class, 'update'])->name('comments.update');
        Route::delete('/{commentId}', [CommentsController::class, 'delete'])->name('comments.delete');
        Route::post('/{commentId}/reactions', [CommentsController::class, 'addReaction'])->name('comments.reactions.add');
        Route::delete('/{commentId}/reactions', [CommentsController::class, 'removeReaction'])->name('comments.reactions.remove');
    });
    
    // Reports Routes
    Route::prefix('reports')->group(function () {
        Route::get('/', [ReportsController::class, 'index'])->name('reports.index');
        Route::get('/user-activity', [ReportsController::class, 'userActivityReport'])->name('reports.user-activity');
        Route::get('/storage-usage', [ReportsController::class, 'storageUsageReport'])->name('reports.storage-usage');
        Route::get('/compliance-status', [ReportsController::class, 'complianceStatusReport'])->name('reports.compliance-status');
        Route::post('/export', [ReportsController::class, 'exportReport'])->name('reports.export');
        Route::post('/schedule', [ReportsController::class, 'scheduleReport'])->name('reports.schedule');
        Route::get('/templates', [ReportsController::class, 'getReportTemplates'])->name('reports.templates');
    });
    
    // Integrations Routes
    Route::prefix('integrations')->group(function () {
        Route::get('/', [IntegrationsController::class, 'index'])->name('integrations.index');
        Route::get('/connect/{integrationId}', [IntegrationsController::class, 'connect'])->name('integrations.connect');
        Route::delete('/{integrationId}/disconnect', [IntegrationsController::class, 'disconnect'])->name('integrations.disconnect');
        Route::get('/{integrationId}/configure', [IntegrationsController::class, 'configure'])->name('integrations.configure');
        Route::get('/available', [IntegrationsController::class, 'getAvailableIntegrations'])->name('integrations.available');
        
        // OAuth callback route (placeholder - you'll need to implement this)
        Route::get('/oauth/{provider}/callback', function ($provider) {
            return "OAuth callback for {$provider}";
        })->name('integrations.oauth.callback');
    });
    
    // Workspace Routes
    Route::prefix('workspaces')->group(function () {
        Route::get('/', [WorkspacesController::class, 'index'])->name('workspaces.index');
        Route::get('/create', [WorkspacesController::class, 'create'])->name('workspaces.create');
        Route::get('/create-form', [WorkspacesController::class, 'createForm'])->name('workspaces.create.form');
        Route::post('/store', [WorkspacesController::class, 'store'])->name('workspaces.store');
        Route::get('/archived', [WorkspacesController::class, 'archived'])->name('workspaces.archived');
        Route::post('/create-client', [WorkspacesController::class, 'createClientWorkspace'])->name('workspaces.create.client');
        
        // Individual workspace routes
        Route::prefix('{workspaceId}')->group(function () {
            Route::get('/', [WorkspacesController::class, 'show'])->name('workspaces.show');
            Route::put('/update', [WorkspacesController::class, 'update'])->name('workspaces.update');
            Route::delete('/delete', [WorkspacesController::class, 'delete'])->name('workspaces.delete');
            
            // Workspace settings
            Route::get('/settings', [WorkspacesController::class, 'settings'])->name('workspaces.settings');
            Route::get('/settings/data', [WorkspacesController::class, 'getSettings'])->name('workspaces.settings.get');
            Route::put('/settings/update', [WorkspacesController::class, 'updateSettings'])->name('workspaces.settings.update');
            
            // Workspace stats and overview
            Route::get('/stats', [WorkspacesController::class, 'getStats'])->name('workspaces.stats');
            Route::get('/overview', [WorkspacesController::class, 'overview'])->name('workspaces.overview');
            Route::get('/activity', [WorkspacesController::class, 'activity'])->name('workspaces.activity');
            
            // Team management
            Route::post('/invite-user', [WorkspacesController::class, 'inviteUser'])->name('workspaces.invite.user');
            Route::get('/team', [WorkspacesController::class, 'team'])->name('workspaces.team');
            Route::get('/clients', [WorkspacesController::class, 'manageClients'])->name('workspaces.clients');
            
            // External collaboration
            Route::get('/external-settings', [WorkspacesController::class, 'getExternalCollaborationSettings'])->name('workspaces.external.settings');
            Route::put('/external-settings', [WorkspacesController::class, 'updateExternalCollaborationSettings'])->name('workspaces.external.settings.update');
            
            // HTMX fragments
            Route::get('/sidebar', [WorkspacesController::class, 'sidebar'])->name('workspaces.sidebar');
            Route::get('/tab/{tab}', [WorkspacesController::class, 'tab'])->name('workspaces.tab');
            
            // Workflow Routes (within workspace context)
            Route::prefix('workflows')->group(function () {
                Route::get('/', [WorkflowsController::class, 'index'])->name('workflows.index');
                Route::get('/create', [WorkflowsController::class, 'create'])->name('workflows.create');
                Route::post('/store', [WorkflowsController::class, 'store'])->name('workflows.store');
                Route::get('/templates', [WorkflowsController::class, 'getTemplates'])->name('workflows.templates');
                Route::post('/import-template/{templateId}', [WorkflowsController::class, 'importTemplate'])->name('workflows.import.template');
                
                // Individual workflow routes
                Route::prefix('{workflowId}')->group(function () {
                    Route::get('/', [WorkflowsController::class, 'show'])->name('workflows.show');
                    Route::put('/update', [WorkflowsController::class, 'update'])->name('workflows.update');
                    Route::delete('/delete', [WorkflowsController::class, 'delete'])->name('workflows.delete');
                    Route::get('/activity', [WorkflowsController::class, 'getActivity'])->name('workflows.activity');
                });
            });
            
            // Files Routes
            Route::prefix('files')->group(function () {
                Route::get('/', [FilesController::class, 'index'])->name('files.index');
                Route::get('/browse/{folderId?}', [FilesController::class, 'browse'])->name('files.browse');
                Route::get('/file/{fileId}', [FilesController::class, 'show'])->name('files.show');
                Route::get('/create', [FilesController::class, 'webCreate'])->name('files.create');
                Route::post('/store', [FilesController::class, 'store'])->name('files.store');
                Route::get('/{fileId}/edit', [FilesController::class, 'webEdit'])->name('files.edit');
                Route::put('/{fileId}/update', [FilesController::class, 'webUpdate'])->name('files.update');
                Route::delete('/{fileId}/delete', [FilesController::class, 'delete'])->name('files.delete');
                
                // File actions
                Route::post('/{fileId}/lock', [FilesController::class, 'lock'])->name('files.lock');
                Route::post('/{fileId}/unlock', [FilesController::class, 'unlock'])->name('files.unlock');
                Route::post('/{fileId}/move', [FilesController::class, 'move'])->name('files.move');
                Route::post('/{fileId}/rename', [FilesController::class, 'rename'])->name('files.rename');
                Route::post('/{fileId}/copy', [FilesController::class, 'copy'])->name('files.copy');
                Route::get('/{fileId}/download', [FilesController::class, 'download'])->name('files.download');
                Route::get('/{fileId}/preview', [FilesController::class, 'preview'])->name('files.preview');
                Route::get('/{fileId}/versions', [FilesController::class, 'getVersions'])->name('files.versions');
                Route::get('/{fileId}/audit-trail', [FilesController::class, 'showAuditTrail'])->name('files.audit-trail');
                
                // File sharing
                Route::post('/{fileId}/share', [FilesController::class, 'share'])->name('files.share');
                Route::put('/share/{shareId}', [FilesController::class, 'updateShare'])->name('files.share.update');
                Route::delete('/share/{shareId}', [FilesController::class, 'revokeShare'])->name('files.share.revoke');
                
                // File organization
                Route::post('/folders/create', [FilesController::class, 'createFolder'])->name('files.folders.create');
                Route::get('/upload-form/{folderId?}', [FilesController::class, 'showUploadForm'])->name('files.upload.form');
                Route::post('/upload/{folderId?}', [FilesController::class, 'uploadMultiple'])->name('files.upload');
                
                // HTMX fragments
                Route::get('/overview', [FilesController::class, 'overview'])->name('files.overview');
                Route::get('/pinned', [FilesController::class, 'pinned'])->name('files.pinned');
                Route::get('/toolbar', [FilesController::class, 'toolbar'])->name('files.toolbar');
                Route::get('/breadcrumbs/{folderId?}', [FilesController::class, 'breadcrumbs'])->name('files.breadcrumbs');
                Route::get('/view/{folderId?}/{mode}', [FilesController::class, 'view'])->name('files.view');
                Route::get('/search/{folderId?}', [FilesController::class, 'searchLocal'])->name('files.search');
                Route::get('/recent-dropdown', [FilesController::class, 'recentDropdown'])->name('files.recent.dropdown');
                Route::get('/bookmarks-dropdown', [FilesController::class, 'bookmarksDropdown'])->name('files.bookmarks.dropdown');
                Route::get('/approvals', [FilesController::class, 'approvals'])->name('files.approvals');
                Route::get('/awaiting-approval', [FilesController::class, 'awaitingApprovalCard'])->name('files.approvals.card');
                Route::get('/locked', [FilesController::class, 'locked'])->name('files.locked');
                Route::get('/locked-card', [FilesController::class, 'lockedFilesCard'])->name('files.locked.card');
            });
            
            // File Requests Routes
            Route::prefix('requests')->group(function () {
                Route::get('/', [FileRequestsController::class, 'index'])->name('file-requests.index');
                Route::get('/create', [FileRequestsController::class, 'createForm'])->name('file-requests.create');
                Route::post('/store', [FileRequestsController::class, 'store'])->name('file-requests.store');
                Route::get('/stats', [FileRequestsController::class, 'stats'])->name('file-requests.stats');
                Route::get('/filters', [FileRequestsController::class, 'filters'])->name('file-requests.filters');
                Route::get('/list', [FileRequestsController::class, 'list'])->name('file-requests.list');
                
                // HTMX fragments
                Route::get('/upload/{requestId}', [FileRequestsController::class, 'upload'])->name('file-requests.upload');
            });
            
            // Tasks Routes
            Route::prefix('tasks')->group(function () {
                Route::get('/', [TasksController::class, 'webIndex'])->name('tasks.index');
                Route::get('/create', [TasksController::class, 'webCreate'])->name('tasks.create');
                Route::post('/store', [TasksController::class, 'store'])->name('tasks.store');
                Route::get('/{taskId}', [TasksController::class, 'webShow'])->name('tasks.show');
                Route::get('/{taskId}/edit', [TasksController::class, 'webEdit'])->name('tasks.edit');
                Route::put('/{taskId}/update', [TasksController::class, 'update'])->name('tasks.update');
                Route::delete('/{taskId}/delete', [TasksController::class, 'delete'])->name('tasks.delete');
                
                // Task actions
                Route::post('/{taskId}/assign', [TasksController::class, 'assign'])->name('tasks.assign');
                Route::post('/{taskId}/comment', [TasksController::class, 'addComment'])->name('tasks.comment');
                Route::post('/{taskId}/status', [TasksController::class, 'updateStatus'])->name('tasks.status');
                Route::post('/{taskId}/inline-status', [TasksController::class, 'inlineStatus'])->name('tasks.status.inline');
                Route::get('/{taskId}/activity', [TasksController::class, 'getActivity'])->name('tasks.activity');
                
                // Workflow & approvals
                Route::post('/{taskId}/workflow', [TasksController::class, 'createApprovalWorkflow'])->name('tasks.workflow.create');
                Route::get('/{taskId}/workflow', [TasksController::class, 'getWorkflowSteps'])->name('tasks.workflow.steps');
                Route::post('/{taskId}/workflow/advance', [TasksController::class, 'advanceWorkflow'])->name('tasks.workflow.advance');
                Route::post('/{taskId}/delegate', [TasksController::class, 'delegateApproval'])->name('tasks.delegate');
                
                // HTMX fragments
                Route::get('/summary', [TasksController::class, 'summary'])->name('tasks.summary');
                Route::get('/summary-card', [TasksController::class, 'summaryCard'])->name('tasks.summary.card');
                Route::get('/controls', [TasksController::class, 'controls'])->name('tasks.controls');
                Route::get('/list', [TasksController::class, 'list'])->name('tasks.list');
                Route::get('/my-tasks-card', [TasksController::class, 'myTasksCard'])->name('tasks.my.card');
                Route::get('/create-form', [TasksController::class, 'createForm'])->name('tasks.create.form');
            });
            
            // Teams & People Routes
            Route::prefix('teams')->group(function () {
                Route::get('/', [TeamsController::class, 'index'])->name('teams.index');
                Route::put('/{teamId}/update', [TeamsController::class, 'update'])->name('teams.update');
                Route::delete('/{teamId}/delete', [TeamsController::class, 'delete'])->name('teams.delete');
                Route::post('/{teamId}/permissions', [TeamsController::class, 'syncPermissions'])->name('teams.permissions.sync');
                Route::post('/{teamId}/users', [TeamsController::class, 'addUser'])->name('teams.users.add');
                Route::delete('/{teamId}/users/{userId}', [TeamsController::class, 'removeUser'])->name('teams.users.remove');
                Route::post('/invite', [TeamsController::class, 'invite'])->name('teams.invite');
                
                // HTMX fragments
                Route::get('/{teamId}', [TeamsController::class, 'team'])->name('teams.show');
                Route::get('/{teamId}/actions', [TeamsController::class, 'actions'])->name('teams.actions');
                Route::get('/table', [TeamsController::class, 'table'])->name('teams.table');
                Route::get('/invite-form', [TeamsController::class, 'inviteForm'])->name('teams.invite.form');
            });
            
            // Company Routes
            Route::prefix('company')->group(function () {
                Route::get('/public-files', [CompaniesController::class, 'getPublicFiles'])->name('company.public-files');
                Route::get('/users', [CompaniesController::class, 'listUsers'])->name('company.users');
                Route::post('/users', [CompaniesController::class, 'addUser'])->name('company.users.add');
                Route::delete('/users/{userId}', [CompaniesController::class, 'removeUser'])->name('company.users.remove');
                Route::post('/users/{userId}/deactivate', [CompaniesController::class, 'deactivateUser'])->name('company.users.deactivate');
            });
        });
    });
    

    
    // Global routes (not workspace-specific)
    Route::get('/about', function () {
        return view('about');
    })->name('about');
    
    Route::get('/help/onboarding', function () {
        return view('help.onboarding');
    })->name('help.onboarding');
    
    Route::get('/feedback', function () {
        return view('feedback');
    })->name('feedback');
    
    // Admin routes
    Route::prefix('admin')->middleware('can:access-admin')->group(function () {
        // Dashboard
        Route::get('/dashboard', [AdminController::class, 'panel'])->name('admin.dashboard');
        
        // System Settings
        Route::get('/settings', [AdminController::class, 'getSettings'])->name('admin.settings');
        Route::put('/settings', [AdminController::class, 'updateSettings'])->name('admin.settings.update');
        Route::post('/settings/security', [AdminController::class, 'updateSecuritySettings'])->name('admin.settings.security');
        Route::post('/branding/upload', [AdminController::class, 'uploadBranding'])->name('admin.branding.upload');
        
        // User Management
        Route::get('/users', [AdminController::class, 'listUsers'])->name('admin.users');
        Route::post('/users', [AdminController::class, 'createUser'])->name('admin.users.create');
        Route::post('/impersonate/{userId}', [AdminController::class, 'impersonate'])->name('admin.impersonate');
        
        // Workspace Management
        Route::get('/workspaces', [AdminController::class, 'indexWorkspaces'])->name('admin.workspaces');
        Route::post('/workspaces/{workspaceId}/archive', [AdminController::class, 'archiveWorkspace'])->name('admin.workspaces.archive');
        Route::get('/workspace-requests', [AdminController::class, 'getWorkspaceRequests'])->name('admin.workspace-requests');
        Route::post('/workspace-requests/{requestId}/approve', [AdminController::class, 'approveWorkspaceRequest'])->name('admin.workspace-requests.approve');
        Route::post('/workspace-requests/{requestId}/reject', [AdminController::class, 'rejectWorkspaceRequest'])->name('admin.workspace-requests.reject');
        
        // API Management
        Route::get('/api/keys', [AdminController::class, 'manageApiKeys'])->name('admin.api.keys');
        Route::post('/api/keys', [AdminController::class, 'createApiKey'])->name('admin.api.keys.create');
        Route::delete('/api/keys/{keyId}', [AdminController::class, 'revokeApiKey'])->name('admin.api.keys.revoke');
        Route::get('/api/metrics', [AdminController::class, 'getApiUsageMetrics'])->name('admin.api.metrics');
        
        // System Health
        Route::get('/system/health', [AdminController::class, 'getSystemHealth'])->name('admin.system.health');
        
        // Storage Management
        Route::get('/storage/quotas', [AdminController::class, 'manageStorageQuotas'])->name('admin.storage.quotas');
        Route::post('/storage/quotas', [AdminController::class, 'manageStorageQuotas'])->name('admin.storage.quotas.update');
        
        // Compliance & Security
        Route::prefix('compliance')->group(function () {
            Route::get('/data-residency', [AdminController::class, 'getDataResidencySettings'])->name('admin.compliance.data-residency');
            Route::put('/data-residency', [AdminController::class, 'updateDataResidencySettings'])->name('admin.compliance.data-residency.update');
            
            Route::get('/reports', [AdminController::class, 'getComplianceReports'])->name('admin.compliance.reports');
            Route::post('/export', [AdminController::class, 'exportComplianceData'])->name('admin.compliance.export');
            
            Route::get('/retention-policies', [AdminController::class, 'configureRetentionPolicies'])->name('admin.compliance.retention-policies');
            Route::post('/retention-policies', [AdminController::class, 'configureRetentionPolicies'])->name('admin.compliance.retention-policies.create');
            
            Route::get('/legal-holds', [AdminController::class, 'manageLegalHolds'])->name('admin.compliance.legal-holds');
            Route::post('/legal-holds', [AdminController::class, 'manageLegalHolds'])->name('admin.compliance.legal-holds.create');
            
            Route::post('/information-barriers', [AdminController::class, 'configureInformationBarriers'])->name('admin.compliance.information-barriers');
        });
        
        // Audit Logs
        Route::get('/audit', [AdminController::class, 'getAuditTrail'])->name('admin.audit');
        Route::post('/audit/export', [AdminController::class, 'exportAuditLogs'])->name('admin.audit.export');
        
        // User Groups & Permissions
        Route::get('/user-groups', [AdminController::class, 'manageUserGroups'])->name('admin.user-groups');
        Route::post('/user-groups', [AdminController::class, 'createUserGroup'])->name('admin.user-groups.create');
        Route::post('/user-groups/permissions', [AdminController::class, 'assignGroupPermissions'])->name('admin.user-groups.permissions');
        
        Route::get('/permission-templates', [AdminController::class, 'getPermissionTemplates'])->name('admin.permission-templates');
        Route::post('/workspaces/{workspaceId}/apply-template', [AdminController::class, 'applyPermissionTemplate'])->name('admin.apply-permission-template');
        
        // Branding & Customization
        Route::prefix('branding')->group(function () {
            Route::post('/logo', [AdminController::class, 'uploadCustomLogo'])->name('admin.branding.logo');
            Route::post('/color-scheme', [AdminController::class, 'setColorScheme'])->name('admin.branding.color-scheme');
            Route::get('/email-templates', [AdminController::class, 'customizeEmailTemplates'])->name('admin.branding.email-templates');
            Route::post('/email-templates', [AdminController::class, 'customizeEmailTemplates'])->name('admin.branding.email-templates.update');
            Route::post('/custom-domain', [AdminController::class, 'setCustomDomain'])->name('admin.branding.custom-domain');
            Route::get('/preview', [AdminController::class, 'getBrandingPreview'])->name('admin.branding.preview');
        });
    });
});

// Public routes (accessible without authentication)
Route::get('/shared/{token}', function ($token) {
    return view('shared.show', ['token' => $token]);
})->name('shared.show');

// Fallback route for 404 errors
Route::fallback(function () {
    return response()->view('errors.404', [], 404);
});

/*
|--------------------------------------------------------------------------
| VIEW-ONLY DEMO ROUTES
| Prefix: /_views   Name prefix: demo.
| These let you preview every Blade page directly, using app.blade.php/base.blade.php layouts.
| They do not replace controller-driven routes.
|--------------------------------------------------------------------------
*/
Route::prefix('_views')->name('demo.')->group(function () {
    // Core pages
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::view('/welcome', 'welcome')->name('welcome');

    // Admin
    Route::view('/admin/dashboard', 'admin.dashboard')->name('admin.dashboard');

    // API
    Route::view('/api/keys', 'api.keys')->name('api.keys');
    Route::view('/api/metrics', 'api.metrics')->name('api.metrics');

    // Audit
    Route::view('/audit', 'audit.index')->name('audit.index');

    // Branding
    Route::view('/branding/email-templates', 'branding.email-templates')->name('branding.email-templates');
    Route::view('/branding/preview', 'branding.preview')->name('branding.preview');

    // Compliance
    Route::view('/compliance/data-residency', 'compliance.data-residency')->name('compliance.data-residency');
    Route::view('/compliance/legal-holds', 'compliance.legal-holds')->name('compliance.legal-holds');
    Route::view('/compliance/reports', 'compliance.reports')->name('compliance.reports');
    Route::view('/compliance/retention-policies', 'compliance.retention-policies')->name('compliance.retention-policies');

    // Files
    Route::view('/files/index', 'files.index')->name('files.index');
    Route::view('/files/show', 'files.show')->name('files.show');

    // Permission templates
    Route::view('/permission-templates', 'permission-templates.index')->name('permission-templates.index');

    // Storage
    Route::view('/storage/quotas', 'storage.quotas')->name('storage.quotas');

    // System
    Route::view('/system/health', 'system.health')->name('system.health');

    // User groups & users
    Route::view('/user-groups', 'user-groups.index')->name('user-groups.index');
    Route::view('/users', 'users.index')->name('users.index');

    // Workspace
    Route::view('/workspace/activity', 'workspace.activity-card')->name('workspace.activity');
    Route::view('/workspace/files', 'workspace.files')->name('workspace.files');
    Route::view('/workspace/overview', 'workspace.overview')->name('workspace.overview');
    Route::view('/workspace/requests', 'workspace.requests')->name('workspace.requests');
    Route::view('/workspace/settings', 'workspace.settings')->name('workspace.settings');
    Route::view('/workspace/tasks', 'workspace.tasks')->name('workspace.tasks');
    Route::view('/workspace/team', 'workspace.team')->name('workspace.team');

    // Workspaces
    Route::view('/workspaces/index', 'workspaces.index')->name('workspaces.index');
    Route::view('/workspaces/requests', 'workspaces.requests')->name('workspaces.requests');
});
Route::fallback(function () {
    abort(404);
});
