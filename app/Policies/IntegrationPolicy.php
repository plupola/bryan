<?php

namespace App\Policies;

use App\Models\Integration;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\Response;

class IntegrationPolicy
{
    /**
     * Determine whether the user can manage the integration.
     */
    public function manage(User $user, Workspace $workspace): bool
    {
        return $workspace->members()
            ->where('user_id', $user->id)
            ->whereHas('role', function($query) {
                $query->whereHas('permissions', function($q) {
                    $q->where('key_name', 'workspace.manage');
                });
            })
            ->exists();
    }
}