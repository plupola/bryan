<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IntegrationsController extends Controller
{
    /**
     * Display a listing of the user's integrations.
     */
    public function index()
    {
        $user = Auth::user();
        
        // Get integrations for all workspaces the user has access to
        $workspaceIds = $user->workspaces()->pluck('workspaces.id');
        
        $integrations = Integration::whereIn('workspace_id', $workspaceIds)
            ->with(['workspace', 'webhookLogs' => function($query) {
                $query->latest()->take(5);
            }])
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->get();

        return view('integrations.index', compact('integrations'));
    }

    /**
     * Connect to a new integration.
     */
    public function connect($integrationId)
    {
        $integration = $this->getAvailableIntegrations()->firstWhere('id', $integrationId);
        
        if (!$integration) {
            return redirect()->route('integrations.index')
                ->with('error', 'Integration not found or not available.');
        }

        // Check if user has permission to manage integrations in any workspace
        $workspaces = Workspace::whereHas('members', function($query) {
            $query->where('user_id', Auth::id())
                  ->whereHas('role', function($q) {
                      $q->whereHas('permissions', function($q2) {
                          $q2->where('key_name', 'workspace.manage');
                      });
                  });
        })->get();

        return view('integrations.connect', compact('integration', 'workspaces'));
    }

    /**
     * Disconnect an integration.
     */
    public function disconnect($integrationId)
    {
        $integration = Integration::findOrFail($integrationId);
        
        // Authorization check - user must have workspace management permissions
        $this->authorize('manage', $integration->workspace);
        
        try {
            // If integration has webhooks, delete them first
            if ($integration->webhooks()->exists()) {
                $integration->webhooks()->delete();
            }
            
            $integration->delete();
            
            return redirect()->route('integrations.index')
                ->with('success', 'Integration disconnected successfully.');
                
        } catch (\Exception $e) {
            return redirect()->route('integrations.index')
                ->with('error', 'Failed to disconnect integration: ' . $e->getMessage());
        }
    }

    /**
     * Configure an integration.
     */
    public function configure($integrationId)
    {
        $integration = Integration::findOrFail($integrationId);
        
        // Authorization check
        $this->authorize('manage', $integration->workspace);
        
        $availableIntegrations = $this->getAvailableIntegrations();
        
        return view('integrations.configure', compact('integration', 'availableIntegrations'));
    }

    /**
     * Get available integrations for the system.
     */
    public function getAvailableIntegrations()
    {
        // This would typically come from a config file or database
        return collect([
            [
                'id' => 'google_drive',
                'name' => 'Google Drive',
                'description' => 'Connect to Google Drive for file synchronization',
                'icon' => 'google-drive',
                'category' => 'storage',
                'config' => [
                    'client_id' => '',
                    'client_secret' => '',
                    'redirect_uri' => route('integrations.oauth.callback', ['google_drive'])
                ]
            ],
            [
                'id' => 'dropbox',
                'name' => 'Dropbox',
                'description' => 'Connect to Dropbox for file backup and sync',
                'icon' => 'dropbox',
                'category' => 'storage',
                'config' => [
                    'app_key' => '',
                    'app_secret' => '',
                    'redirect_uri' => route('integrations.oauth.callback', ['dropbox'])
                ]
            ],
            [
                'id' => 'slack',
                'name' => 'Slack',
                'description' => 'Get notifications in Slack channels',
                'icon' => 'slack',
                'category' => 'communication',
                'config' => [
                    'client_id' => '',
                    'client_secret' => '',
                    'redirect_uri' => route('integrations.oauth.callback', ['slack'])
                ]
            ],
            [
                'id' => 'microsoft_teams',
                'name' => 'Microsoft Teams',
                'description' => 'Integrate with Microsoft Teams for notifications',
                'icon' => 'microsoft-teams',
                'category' => 'communication',
                'config' => [
                    'client_id' => '',
                    'client_secret' => '',
                    'redirect_uri' => route('integrations.oauth.callback', ['microsoft_teams'])
                ]
            ],
            [
                'id' => 'zapier',
                'name' => 'Zapier',
                'description' => 'Connect with Zapier for workflow automation',
                'icon' => 'zapier',
                'category' => 'automation',
                'config' => [
                    'api_key' => ''
                ]
            ]
        ]);
    }
}