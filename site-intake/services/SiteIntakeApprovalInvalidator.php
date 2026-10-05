<?php

namespace App\SiteIntake\Services;

use App\Models\ProjectOnboardingProfile;
use App\Services\Logging\ActivityLogger;

final class SiteIntakeApprovalInvalidator
{
    public function invalidateProjects(array $projectIds, string $reason, array $changedFields = []): void
    {
        ProjectOnboardingProfile::query()->whereIn('project_id', array_unique($projectIds))->whereIn('status', ['approved_for_factory', 'exported'])->get()->each(function (ProjectOnboardingProfile $profile) use ($reason, $changedFields): void {
            $profile->exports()->where('current_key', 'current')->update(['current_key' => null, 'invalidated_at' => now(), 'invalidation_reason' => $reason]);
            $profile->update(['status' => 'under_review', 'invalidated_at' => now(), 'invalidation_reason' => $reason, 'approved_at' => null, 'approved_by' => null, 'approved_profile_hash' => null, 'exported_at' => null, 'exported_by' => null, 'current_export_identifier' => null]);
            app(ActivityLogger::class)->record('onboarding.factory_approval_invalidated_by_source_change', request(), auth()->user(), 'onboarding', $profile, metadata: ['project_id' => $profile->project_id, 'reason' => $reason, 'changed_fields' => $changedFields]);
        });
    }
}
