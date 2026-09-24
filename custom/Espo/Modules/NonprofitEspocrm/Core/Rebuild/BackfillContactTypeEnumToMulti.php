<?php

namespace Espo\Modules\NonprofitEspocrm\Core\Rebuild;

use Espo\Core\Rebuild\RebuildAction;
use Espo\Core\Utils\Log;
use Espo\Modules\NonprofitEspocrm\Tools\ContactTypeEnumToMulti;
use Throwable;

/**
 * Copy leftover Contact.contactType enum strings into multiEnum lists.
 *
 * Production deploy is rsync + `php command.php rebuild` only. The console
 * command is not part of that path. Without this action the ORM reads a bare
 * `Volunteer` varchar as null (jsonArray) and primary filters, which use
 * ArrayValue, return nobody. The next Contact save then stores that null.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
 *
 * @noinspection PhpUnused
 */
class BackfillContactTypeEnumToMulti implements RebuildAction
{
    public function __construct(
        private ContactTypeEnumToMulti $contactTypeEnumToMulti,
        private Log $log,
    ) {}

    public function process(): void
    {
        if (!$this->contactTypeEnumToMulti->isFieldArray()) {
            $this->log->warning(
                'Contact.contactType enum copy skipped: attribute is not jsonArray yet.'
            );

            return;
        }

        try {
            $contacts = [];

            foreach ($this->contactTypeEnumToMulti->findContacts() as $contact) {
                $contacts[] = $contact;
            }

            $stats = $this->contactTypeEnumToMulti->copyAll($contacts, true);
        } catch (Throwable $e) {
            $this->log->error(
                'Contact.contactType enum copy failed: ' . $e->getMessage()
            );

            return;
        }

        if ($stats['copied'] === 0 && $stats['failed'] === 0) {
            return;
        }

        $this->log->warning(sprintf(
            'Contact.contactType enum copy: copied=%d failed=%d skippedAlreadyArray=%d skippedEmpty=%d',
            $stats['copied'],
            $stats['failed'],
            $stats['skippedAlreadyArray'],
            $stats['skippedEmpty']
        ));
    }
}
