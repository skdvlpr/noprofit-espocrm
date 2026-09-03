<?php

declare(strict_types=1);

namespace Espo\Modules\NonprofitEspocrm\Hooks\Contact;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Utils\Language;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Non-admin actors may create/edit own Contacts, but must not bind
 * linkedUser / portalUser to another account. That path lets a
 * volunteer-owned Contact:
 *  - push isOccasional onto the victim User (SyncOccasionalToUser + SKIP_ALL)
 *  - receive the victim's profile on the next User save (UserContactProfileSync)
 *  - become an unordered "primary" linked contact for that user
 *
 * Admins and the system user may still set either link. SKIP_ALL is trusted
 * (profile-sync updates).
 *
 * @see https://docs.espocrm.com/development/hooks/
 * @see https://docs.espocrm.com/development/acl/
 *
 * @implements BeforeSave<\Espo\Modules\Crm\Entities\Contact>
 */
class ProtectLinkedUser implements BeforeSave
{
    public static int $order = 4;

    public function __construct(
        private User $user,
        private Language $language,
    ) {}

    /**
     * @throws Forbidden
     */
    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        if ($this->user->isAdmin() || $this->user->isSystem()) {
            return;
        }

        $this->assertLinkedUserAllowed($entity);
        $this->assertPortalUserUnchanged($entity);
    }

    /**
     * @throws Forbidden
     */
    private function assertLinkedUserAllowed(Entity $entity): void
    {
        if (!$entity->isNew() && !$entity->isAttributeChanged('linkedUserId')) {
            return;
        }

        $newId = trim((string) ($entity->get('linkedUserId') ?? ''));

        // Unlink is allowed (remediates a bad bind). Self-link is allowed
        // (UserContactProfileSync create path when a volunteer saves their User).
        if ($newId === '' || $newId === $this->user->getId()) {
            return;
        }

        throw new Forbidden($this->msg('cannotLinkContactToOtherUser'));
    }

    /**
     * @throws Forbidden
     */
    private function assertPortalUserUnchanged(Entity $entity): void
    {
        if (!$entity->isNew() && !$entity->isAttributeChanged('portalUserId')) {
            return;
        }

        $newId = trim((string) ($entity->get('portalUserId') ?? ''));

        if ($newId === '') {
            return;
        }

        throw new Forbidden($this->msg('cannotLinkContactToPortalUser'));
    }

    private function msg(string $key): string
    {
        $translated = $this->language->translate($key, 'messages', 'Contact');

        if (is_string($translated) && $translated !== '' && $translated !== $key) {
            return $translated;
        }

        return $key === 'cannotLinkContactToPortalUser'
            ? 'Cannot link this contact to a portal user.'
            : 'Cannot link this contact to another user.';
    }
}
