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
}
