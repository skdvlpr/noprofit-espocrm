<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\WebPush;

use Espo\ORM\Entity;

/**
 * Preference gates for browser Web Push (parity with native In-App assignment lists).
 */
class WebPushPreferenceChecker
{
    /**
     * Master switch + optional per-entity ignore list (same semantics as
     * Preferences.assignmentNotificationsIgnoreEntityTypeList).
     */
    public function allowsEntity(Entity $preferences, ?string $entityType): bool
    {
        if (!$preferences->get('webPushEnabled')) {
            return false;
        }

        if ($entityType === null || $entityType === '') {
            return true;
        }

        $ignoreList = $preferences->get('assignmentPushNotificationsIgnoreEntityTypeList') ?? [];

        if (!is_array($ignoreList)) {
            return true;
        }

        return !in_array($entityType, $ignoreList, true);
    }
}
