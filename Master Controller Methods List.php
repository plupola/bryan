Master Controller Methods List (My App Feature Parity) 
<? php

AuthController

public function login() {}
public function logout() {}
public function register() {}
public function resetPassword() {}
public function forgotPassword() {}
public function changePassword() {}
public function twoFactorSetup() {}
public function twoFactorVerify() {}
public function twoFactorDisable() {}

AdminController

// Core Administration
public function panel() {}
public function getSettings() {}
public function updateSettings() {}
public function uploadBranding() {}
public function updateSecuritySettings() {}
public function impersonate() {}

// User & Workspace Management
public function listUsers() {}
public function createUser() {}
public function getWorkspaceRequests() {}
public function approveWorkspaceRequest() {}
public function rejectWorkspaceRequest() {}
public function indexWorkspaces() {}
public function archiveWorkspace() {}

// Advanced Security & Compliance 
public function getDataResidencySettings() {}
public function updateDataResidencySettings() {}
public function getComplianceReports() {}
public function exportComplianceData() {}
public function configureInformationBarriers() {}
public function getAuditTrail() {}
public function exportAuditLogs() {}
public function configureRetentionPolicies() {}
public function manageLegalHolds() {}
public function getSystemHealth() {}
public function manageStorageQuotas() {}

// API & Integration Management 
public function manageApiKeys() {}
public function createApiKey() {}
public function revokeApiKey($keyId) {}
public function getApiUsageMetrics() {}

// User & Permission Governance 
public function manageUserGroups() {}
public function createUserGroup() {}
public function assignGroupPermissions() {}
public function getPermissionTemplates() {}
public function applyPermissionTemplate($workspaceId) {}

// Customization & Branding 
public function uploadCustomLogo() {}
public function setColorScheme() {}
public function customizeEmailTemplates() {}
public function setCustomDomain() {}
public function getBrandingPreview() {}

WorkspacesController

// Core CRUD
public function index() {}
public function create() {}
public function createForm() {}
public function show($workspaceId) {}
public function update($workspaceId) {}
public function delete($workspaceId) {}

// User Collaboration
public function inviteUser($workspaceId) {}
public function archived() {}

// Settings & Configuration
public function getSettings($workspaceId) {}
public function updateSettings($workspaceId) {}
public function settings($workspaceId) {}

// Stats & Overview
public function getStats($workspaceId) {}
public function overview($workspaceId) {}
public function activity($workspaceId) {}

// Client & External Collaboration 
public function createClientWorkspace() {}
public function manageClients($workspaceId) {}
public function getExternalCollaborationSettings($workspaceId) {}
public function updateExternalCollaborationSettings($workspaceId) {}

// HTMX Fragments
public function sidebar($workspaceId) {}
public function tab($workspaceId, $tab) {}
public function team($workspaceId) {}

ClientWorkspacesController 

public function index() {}
public function show($clientWorkspaceId) {}
public function updateAccessRules($clientWorkspaceId) {}

FilesController

// Core CRUD & Actions
public function index($workspaceId, $folderId = null) {}
public function browse($workspaceId, $folderId = null) {}
public function show($fileId) {}
public function delete($fileId) {}
public function showAuditTrail($fileId) {}
public function copy($fileId) {}
public function download($fileId) {}
public function lock($fileId) {}
public function move($fileId) {}
public function preview($fileId) {}
public function rename($fileId) {}
public function unlock($fileId) {}
public function createFolder($workspaceId, $parentFolderId = null) {}
public function upload($workspaceId, $folderId = null) {}

// Sharing & Collaboration
public function share($fileId) {}
public function updateShare($shareId) {}
public function revokeShare($shareId) {}

// Version Control
public function getVersions($fileId) {}
public function compareVersions($fileId) {}
public function restoreVersion($fileId, $versionId) {}

// Security & Protection
public function setPassword($fileId) {}
public function remoteWipe($fileId) {}

// Bookmarks
public function addBookmark($fileId) {}
public function removeBookmark($fileId) {}
public function getBookmarks() {}

// Advanced Security & Governance 
public function applyClassification($fileId) {}
public function getClassificationTemplates() {}
public function watermarkPreview($fileId) {}
public function setWatermarkPolicy($fileId) {}
public function applyRetentionPolicy($fileId) {}
public function placeLegalHold($fileId) {}
public function releaseLegalHold($fileId) {}
public function getFileRelationships($fileId) {}
public function createFileRelationship($fileId) {}
public function bulkUpdateMetadata() {}

// Mobile & Offline 
public function markForOffline($fileId) {}
public function getOfflineContent() {}
public function syncOfflineChanges() {}
public function getMobilePreview($fileId) {}

// HTMX Fragments & Cards
public function overview() {}
public function pinned($workspaceId) {}
public function toolbar($workspaceId) {}
public function breadcrumbs($workspaceId, $folderId = null) {}
public function view($workspaceId, $folderId = null, $mode = 'list') {}
public function searchLocal($workspaceId, $folderId = null) {}
public function recentDropdown() {}
public function bookmarksDropdown() {}
public function approvals() {}
public function awaitingApprovalCard() {}
public function locked() {}
public function lockedFilesCard() {}

FileRequestsController

public function index($workspaceId) {}
public function upload($requestId) {}
public function stats($workspaceId) {}
public function create() {}
public function createForm($workspaceId) {}
public function store(Request $request, $workspaceId) {}
public function filters($workspaceId) {}
public function list($workspaceId) {}



TasksController

// Core CRUD & Actions
public function index($workspaceId) {}
public function create() {}
public function show($taskId) {}
public function update($taskId) {}
public function delete($taskId) {}
public function getActivity($taskId) {}
public function assign($taskId) {}
public function addComment($taskId) {}
public function updateStatus($taskId) {}
public function inlineStatus($taskId) {}

// Workflow & Approvals 
public function createApprovalWorkflow($taskId) {}
public function getWorkflowSteps($taskId) {}
public function advanceWorkflow($taskId) {}
public function delegateApproval($taskId) {}

// HTMX Fragments & Cards
public function summary() {}
public function summaryCard($workspaceId) {}
public function controls($workspaceId) {}
public function list($workspaceId) {}
public function myTasksCard() {}
public function createForm($workspaceId) {}
public function store(Request $request, $workspaceId) {}

WorkflowsController 

public function index($workspaceId) {}
public function create() {}
public function show($workflowId) {}
public function update($workflowId) {}
public function delete($workflowId) {}
public function getTemplates() {}
public function importTemplate($templateId) {}
public function getActivity($workflowId) {}

TeamsController

// Core Actions
public function index() {}
public function update($teamId) {}
public function delete($teamId) {}
public function syncPermissions($teamId) {}
public function addUser($teamId) {}
public function removeUser($teamId) {}

// Web & HTMX Fragments
public function team($workspaceId) {}
public function actions($workspaceId) {}
public function table($workspaceId) {}
public function inviteForm($workspaceId) {}
public function invite(Request $request, $workspaceId) {}

CompaniesController

public function getPublicFiles() {}
public function listUsers() {}
public function addUser() {}
public function removeUser() {}
public function deactivateUser() {}

CommentsController

public function update($commentId) {}
public function delete($commentId) {}
public function addReaction($commentId) {}
public function removeReaction($commentId) {}
public function index() {}
public function create() {}

ProfileController

public function show() {}
public function update() {}
public function updateAvatar() {}
public function updatePassword() {}
public function getSessions() {}
public function invalidateSession($sessionId) {}
public function menu() {}

SearchController

public function global() {}
public function activityFeed() {}
public function workspaceActivity($workspaceId) {}
public function quick() {}

// Advanced Search 
public function advancedSearch() {}
public function saveSearchQuery() {}
public function getSavedSearches() {}
public function searchWithinResults() {}
public function getSearchAnalytics() {}

ReportsController 

public function index() {}
public function userActivityReport() {}
public function storageUsageReport() {}
public function complianceStatusReport() {}
public function exportReport() {}
public function scheduleReport() {}
public function getReportTemplates() {}

IntegrationsController 

public function index() {}
public function connect($integrationId) {}
public function disconnect($integrationId) {}
public function configure($integrationId) {}
public function getAvailableIntegrations() {}

NotificationsController

public function index() {}
public function getPreferences() {}
public function updatePreferences() {}
public function markAllRead() {}
public function getUnreadCount() {}
public function markAsRead($notificationId) {}
public function dropdown() {}

API to Web Bridge Methods

// (To be added to relevant controllers: FilesController, TasksController, etc.)


public function webIndex() {}
public function webShow($id) {}
public function webCreate() {}
public function webEdit($id) {}