<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as RequestFacade;

/**
 * The single place every part of the app writes an ActivityLog row through,
 * so the action-slug catalog (ActivityLog::ACTIONS) and tenant-context
 * resolution stay consistent no matter which controller is logging.
 */
class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(string $action, ?Model $subject = null, ?string $description = null, array $properties = [], ?User $actor = null): ActivityLog
    {
        $actor ??= Auth::user();

        return ActivityLog::create([
            'client_id' => $this->resolveClientId($subject, $actor),
            'business_id' => $this->resolveBusinessId($subject, $actor),
            'branch_id' => $this->resolveBranchId($subject, $actor),
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'properties' => $properties ?: null,
            'ip_address' => RequestFacade::ip(),
            'user_agent' => RequestFacade::userAgent(),
        ]);
    }

    /**
     * Prefer the AFFECTED tenant (the subject), not the actor's own — a
     * Super Admin acting on Client #5 should log against Client #5, not
     * against the Super Admin's own (null) client_id.
     */
    private function resolveClientId(?Model $subject, ?User $actor): ?int
    {
        if ($subject instanceof Client) {
            return $subject->id;
        }

        if ($subject && isset($subject->client_id)) {
            return $subject->client_id;
        }

        return $actor?->client_id;
    }

    private function resolveBusinessId(?Model $subject, ?User $actor): ?int
    {
        if ($subject instanceof Business) {
            return $subject->id;
        }

        if ($subject && isset($subject->business_id)) {
            return $subject->business_id;
        }

        return $actor?->business_id;
    }

    private function resolveBranchId(?Model $subject, ?User $actor): ?int
    {
        if ($subject instanceof Branch) {
            return $subject->id;
        }

        if ($subject && isset($subject->branch_id)) {
            return $subject->branch_id;
        }

        return $actor?->branch_id;
    }
}
