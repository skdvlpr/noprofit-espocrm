<?php

namespace Espo\Modules\NonprofitEspocrm\Classes\ConsoleCommands;

use Espo\Core\Console\Command;
use Espo\Core\Console\Command\Params;
use Espo\Core\Console\IO;
use Espo\Modules\NonprofitEspocrm\Tools\ContactTypeEnumToMulti;

/**
 * Copy Contact.contactType enum strings into multiEnum lists (one-shot).
 *
 * Dry-run by default. Pass --apply to write. Apply is refused while the
 * attribute is still an enum (ship multiEnum metadata + rebuild first).
 * Iterates Contacts via ORM; reads the leftover varchar through the
 * entity → table mapping; saves through the ORM so ArrayValue is populated.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-console-commands.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
 *
 * @noinspection PhpUnused
 */
class CopyContactTypeEnumToMulti implements Command
{
    public function __construct(
        private ContactTypeEnumToMulti $contactTypeEnumToMulti
    ) {}

    public function run(Params $params, IO $io): void
    {
        $apply = $params->hasFlag('apply');

        $mode = $apply ? 'apply' : 'dry-run (pass --apply to write)';
        $io->writeLine('copyContactTypeEnumToMulti: ' . $mode);

        if (!$this->contactTypeEnumToMulti->isFieldArray()) {
            $io->writeLine(
                'Contact.contactType is not a jsonArray attribute yet ' .
                '(ship multiEnum metadata and rebuild first).'
            );

            if ($apply) {
                $io->writeLine('Refusing to write. Nothing changed.');
                $io->setExitStatus(1);

                return;
            }
        }

        $stats = $this->contactTypeEnumToMulti->copyAll(
            $this->contactTypeEnumToMulti->findContacts(),
            $apply
        );

        $io->writeLine('copied=' . $stats['copied']);
        $io->writeLine('skippedAlreadyArray=' . $stats['skippedAlreadyArray']);
        $io->writeLine('skippedEmpty=' . $stats['skippedEmpty']);
        $io->writeLine('failed=' . $stats['failed']);

        if ($stats['failed'] > 0) {
            $io->setExitStatus(1);
        }
    }
}
