<?php

namespace Espo\Modules\NonprofitEspocrm\Tools;

/**
 * Non-ACL Safehouse defaults (Stripe sync usernames and similar config constants).
 * Roles are managed only via Administration → Roles (no auto-provision).
 */
final class SafehouseDefaults
{
    /**
     * Trusted Stripe sync actors (ProtectStripeSourcedFields bypass).
     *
     * @var list<string>
     */
    public const STRIPE_SYNC_USER_NAMES = [
        'website',
        'site_safehouse.community',
    ];

    /**
     * Ensure these entity types appear in Admin → Settings → Notifications
     * (`assignmentNotificationsEntityList`). Espo core defaults are only
     * Meeting/Call/Email — Task is omitted unless an admin adds it.
     *
     * @var list<string>
     */
    public const ASSIGNMENT_NOTIFICATION_ENTITY_TYPES = [
        'Task',
    ];
}
