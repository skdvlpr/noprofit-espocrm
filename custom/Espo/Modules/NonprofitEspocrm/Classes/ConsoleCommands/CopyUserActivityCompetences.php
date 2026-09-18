<?php

namespace Espo\Modules\NonprofitEspocrm\Classes\ConsoleCommands;

use Espo\Core\Console\Command;
use Espo\Core\Console\Command\Params;
use Espo\Core\Console\IO;
use Espo\Modules\NonprofitEspocrm\Tools\ContactActivityCompetences;
use Espo\ORM\EntityManager;

/**
 * Copy User activityCompetences onto linked Volunteer/Employee Contacts.
 *
 * Dry-run by default. Pass --apply to write. When the User field is
 * notStorable, reads a leftover DB column if the Contact list is still empty
 * (production one-shot after this tree). Does not overwrite a non-empty Contact.
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-console-commands.md
 *
 * @noinspection PhpUnused
 */
class CopyUserActivityCompetences implements Command
{
    public function __construct(
        private EntityManager $entityManager,
        private ContactActivityCompetences $contactActivityCompetences
    ) {}

    public function run(Params $params, IO $io): void
    {
        $apply = $params->hasFlag('apply');

        $users = $this->entityManager
            ->getRDBRepository('User')
            ->where(['type!=' => 'system'])
            ->find();

        $stats = $this->contactActivityCompetences->copyFromUsers($users, $apply);

        $mode = $apply ? 'apply' : 'dry-run (pass --apply to write)';
        $io->writeLine('copyUserActivityCompetences: ' . $mode);
        $io->writeLine('copied=' . $stats['copied']);
        $io->writeLine('skippedNoContact=' . $stats['skippedNoContact']);
        $io->writeLine('skippedNotPersonnel=' . $stats['skippedNotPersonnel']);
        $io->writeLine('skippedEmpty=' . $stats['skippedEmpty']);
    }
}
