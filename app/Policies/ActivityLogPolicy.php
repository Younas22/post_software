<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

/**
 * Super Admin sees every log row (platform-wide audit trail); a client-side
 * user with activity_logs.view would only see rows matching their own
 * client_id (enforced by scoping the query in the controller once a
 * client-side activity log screen exists — none does yet, per Phase 8's
 * Super-Admin-only scope).
 */
class ActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('activity_logs.view');
    }

    public function view(User $user, ActivityLog $activityLog): bool
    {
        return $user->is_super_admin
            || ($user->hasPermission('activity_logs.view') && $activityLog->client_id === $user->client_id);
    }
}
