<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TestDataSeeder extends Seeder
{
    public function run()
    {
        // Disable foreign key checks for easier seeding
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Clear existing data
        $this->truncateTables();

        // Create companies
        $companyId = DB::table('companies')->insertGetId([
            'name' => 'Acme Corporation',
            'domain' => 'acme.com',
            'description' => 'A leading technology company',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create test users
        $adminId = DB::table('users')->insertGetId([
            'company_id' => $companyId,
            'email' => 'admin@example.com',
            'password_hash' => Hash::make('Admin123!'),
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'is_active' => 1,
            'is_admin' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'company_id' => $companyId,
            'email' => 'user@example.com',
            'password_hash' => Hash::make('User123!'),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => 1,
            'is_admin' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $clientId = DB::table('users')->insertGetId([
            'company_id' => $companyId,
            'email' => 'client@example.com',
            'password_hash' => Hash::make('Client123!'),
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'is_active' => 1,
            'is_admin' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create workspaces
        $workspace1Id = DB::table('workspaces')->insertGetId([
            'name' => 'Marketing Campaign',
            'description' => 'Workspace for Q4 marketing campaign materials',
            'owner_user_id' => $adminId,
            'storage_quota' => 5368709120, // 5GB
            'storage_used' => 104857600, // 100MB
            'is_archived' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $workspace2Id = DB::table('workspaces')->insertGetId([
            'name' => 'Product Development',
            'description' => 'Workspace for new product documentation',
            'owner_user_id' => $adminId,
            'storage_quota' => 10737418240, // 10GB
            'storage_used' => 524288000, // 500MB
            'is_archived' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Get system roles
        $ownerRoleId = DB::table('roles')->where('key_name', 'workspace_owner')->value('id');
        $memberRoleId = DB::table('roles')->where('key_name', 'workspace_member')->value('id');
        $clientRoleId = DB::table('roles')->insertGetId([
            'key_name' => 'client',
            'label' => 'Client',
            'workspace_id' => $workspace1Id,
            'is_system_role' => 0,
        ]);

        // Add users to workspaces
        DB::table('workspace_members')->insert([
            // Admin is owner of both workspaces
            [
                'workspace_id' => $workspace1Id,
                'user_id' => $adminId,
                'role_id' => $ownerRoleId,
                'status' => 'active',
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workspace_id' => $workspace2Id,
                'user_id' => $adminId,
                'role_id' => $ownerRoleId,
                'status' => 'active',
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Regular user is member of both workspaces
            [
                'workspace_id' => $workspace1Id,
                'user_id' => $userId,
                'role_id' => $memberRoleId,
                'status' => 'active',
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workspace_id' => $workspace2Id,
                'user_id' => $userId,
                'role_id' => $memberRoleId,
                'status' => 'active',
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Client user has client role in workspace 1
            [
                'workspace_id' => $workspace1Id,
                'user_id' => $clientId,
                'role_id' => $clientRoleId,
                'status' => 'active',
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Create folders
        $rootFolder1Id = DB::table('folders')->insertGetId([
            'name' => 'Root',
            'parent_id' => null,
            'workspace_id' => $workspace1Id,
            'path' => '/',
            'depth' => 0,
            'created_by' => $adminId,
            'is_deleted' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $marketingFolderId = DB::table('folders')->insertGetId([
            'name' => 'Marketing Materials',
            'parent_id' => $rootFolder1Id,
            'workspace_id' => $workspace1Id,
            'path' => '/Marketing Materials',
            'depth' => 1,
            'created_by' => $adminId,
            'is_deleted' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $presentationsFolderId = DB::table('folders')->insertGetId([
            'name' => 'Presentations',
            'parent_id' => $marketingFolderId,
            'workspace_id' => $workspace1Id,
            'path' => '/Marketing Materials/Presentations',
            'depth' => 2,
            'created_by' => $adminId,
            'is_deleted' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rootFolder2Id = DB::table('folders')->insertGetId([
            'name' => 'Root',
            'parent_id' => null,
            'workspace_id' => $workspace2Id,
            'path' => '/',
            'depth' => 0,
            'created_by' => $adminId,
            'is_deleted' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create documents
        $document1Id = $this->createDocument($workspace1Id, $presentationsFolderId, $adminId, 
            'Q4 Marketing Strategy.pptx', 5242880, 'application/vnd.openxmlformats-officedocument.presentationml.presentation');
        
        $document2Id = $this->createDocument($workspace1Id, $marketingFolderId, $userId, 
            'Campaign Budget.xlsx', 2097152, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        
        $document3Id = $this->createDocument($workspace2Id, $rootFolder2Id, $adminId, 
            'Product Requirements Document.docx', 1048576, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        // Create document versions
        $doc1VersionId = $this->createDocumentVersion($document1Id, 1, $adminId, 'Q4 Marketing Strategy v1.pptx');
        $doc2VersionId = $this->createDocumentVersion($document2Id, 1, $userId, 'Campaign Budget v1.xlsx');
        $doc3VersionId = $this->createDocumentVersion($document3Id, 1, $adminId, 'Product Requirements Document v1.docx');

        // Create tasks
        $task1Id = DB::table('tasks')->insertGetId([
            'title' => 'Review Q4 Marketing Strategy',
            'description' => 'Please review the Q4 marketing strategy presentation and provide feedback',
            'due_at' => now()->addDays(7),
            'priority' => 'high',
            'status' => 'open',
            'created_by' => $adminId,
            'assigned_to' => $userId,
            'document_id' => $document1Id,
            'workspace_id' => $workspace1Id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $task2Id = DB::table('tasks')->insertGetId([
            'title' => 'Finalize Campaign Budget',
            'description' => 'Update the campaign budget with final numbers from finance',
            'due_at' => now()->addDays(3),
            'priority' => 'medium',
            'status' => 'in_progress',
            'created_by' => $adminId,
            'assigned_to' => $userId,
            'document_id' => $document2Id,
            'workspace_id' => $workspace1Id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create file requests
        $fileRequestId = DB::table('file_requests')->insertGetId([
            'workspace_id' => $workspace1Id,
            'folder_id' => $marketingFolderId,
            'created_by' => $adminId,
            'title' => 'Client Marketing Assets',
            'instructions' => 'Please upload your logo and brand guidelines for our marketing campaign',
            'token' => Str::random(32),
            'opens_at' => now()->subDays(1),
            'closes_at' => now()->addDays(14),
            'require_email' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create comments
        DB::table('comments')->insert([
            [
                'workspace_id' => $workspace1Id,
                'resource_type' => 'document',
                'resource_id' => $document1Id,
                'author_id' => $userId,
                'parent_id' => null,
                'body' => 'The slides look great! Should we add more data about the target audience?',
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subHours(2),
            ],
            [
                'workspace_id' => $workspace1Id,
                'resource_type' => 'document',
                'resource_id' => $document1Id,
                'author_id' => $adminId,
                'parent_id' => null,
                'body' => 'Good point. I\'ll add a slide with demographic data.',
                'created_at' => now()->subHours(1),
                'updated_at' => now()->subHours(1),
            ],
        ]);

        // Create audit logs
        DB::table('audit_logs')->insert([
            [
                'workspace_id' => $workspace1Id,
                'actor_user_id' => $adminId,
                'action' => 'workspace.created',
                'resource_type' => 'workspace',
                'resource_id' => $workspace1Id,
                'ip_address' => inet_pton('192.168.1.100'),
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'metadata' => json_encode(['name' => 'Marketing Campaign']),
                'created_at' => now()->subDays(5),
            ],
            [
                'workspace_id' => $workspace1Id,
                'actor_user_id' => $adminId,
                'action' => 'document.uploaded',
                'resource_type' => 'document',
                'resource_id' => $document1Id,
                'ip_address' => inet_pton('192.168.1.100'),
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'metadata' => json_encode(['filename' => 'Q4 Marketing Strategy.pptx', 'size' => '5MB']),
                'created_at' => now()->subDays(3),
            ],
            [
                'workspace_id' => $workspace1Id,
                'actor_user_id' => $userId,
                'action' => 'task.assigned',
                'resource_type' => 'task',
                'resource_id' => $task1Id,
                'ip_address' => inet_pton('192.168.1.101'),
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                'metadata' => json_encode(['task_title' => 'Review Q4 Marketing Strategy']),
                'created_at' => now()->subDays(2),
            ],
        ]);

        // Create workspace settings
        DB::table('workspace_settings')->insert([
            [
                'workspace_id' => $workspace1Id,
                'k' => 'external_collaboration.enabled',
                'v' => json_encode(true),
                'updated_at' => now(),
            ],
            [
                'workspace_id' => $workspace1Id,
                'k' => 'external_collaboration.allow_guest_uploads',
                'v' => json_encode(true),
                'updated_at' => now(),
            ],
            [
                'workspace_id' => $workspace2Id,
                'k' => 'external_collaboration.enabled',
                'v' => json_encode(false),
                'updated_at' => now(),
            ],
        ]);

        // Create teams
        $marketingTeamId = DB::table('teams')->insertGetId([
            'workspace_id' => $workspace1Id,
            'name' => 'Marketing Team',
            'description' => 'Team responsible for marketing campaigns',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Add users to teams
        DB::table('team_members')->insert([
            [
                'team_id' => $marketingTeamId,
                'user_id' => $adminId,
                'role' => 'manager',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'team_id' => $marketingTeamId,
                'user_id' => $userId,
                'role' => 'member',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        echo "Test data seeded successfully!\n";
        echo "Admin user: admin@example.com / Admin123!\n";
        echo "Regular user: user@example.com / User123!\n";
        echo "Client user: client@example.com / Client123!\n";

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function truncateTables()
    {
        $tables = [
            'team_members', 'teams', 'workspace_settings', 'audit_logs', 'comments',
            'file_requests', 'task_assignees', 'task_comments', 'tasks',
            'document_versions', 'documents', 'folders', 'workspace_members',
            'workspaces', 'users', 'companies'
        ];

        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }
    }

    private function createDocument($workspaceId, $folderId, $uploadedBy, $originalName, $sizeBytes, $mimeType)
    {
        $uuid = Str::uuid();
        
        return DB::table('documents')->insertGetId([
            'uuid_bin' => $this->uuidToBin($uuid),
            'original_name' => $originalName,
            'folder_id' => $folderId,
            'workspace_id' => $workspaceId,
            'uploaded_by' => $uploadedBy,
            'size_bytes' => $sizeBytes,
            'mime_type' => $mimeType,
            'latest_version' => 1,
            'checksum' => hash('sha256', $originalName . time(), true),
            'confidentiality' => 'internal',
            'is_locked' => 0,
            'is_deleted' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createDocumentVersion($documentId, $versionNo, $uploadedBy, $originalName)
    {
        $uuid = Str::uuid();
        
        return DB::table('document_versions')->insertGetId([
            'uuid_bin' => $this->uuidToBin($uuid),
            'document_id' => $documentId,
            'version_no' => $versionNo,
            'storage_key' => 'documents/' . Str::random(40) . '.' . pathinfo($originalName, PATHINFO_EXTENSION),
            'original_name' => $originalName,
            'mime_type' => 'application/octet-stream',
            'extension' => pathinfo($originalName, PATHINFO_EXTENSION),
            'size_bytes' => rand(1024, 10485760), // 1KB to 10MB
            'sha256' => hash('sha256', $originalName . time(), true),
            'uploaded_by' => $uploadedBy,
            'uploaded_at' => now(),
            'change_note' => 'Initial version',
        ]);
    }

    private function uuidToBin($uuid)
    {
        return hex2bin(str_replace('-', '', $uuid));
    }
}
