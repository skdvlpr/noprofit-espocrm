<?php

declare(strict_types=1);

namespace Espo\Modules\NonprofitEspocrm\Classes\ConsoleCommands;

use Espo\Core\Console\Command;
use Espo\Core\Console\Command\Params;
use Espo\Core\Console\IO;
use Espo\Entities\User;
use Espo\ORM\EntityManager;
use Espo\Tools\UserSecurity\Password\RecoveryService;
use Espo\Tools\UserSecurity\Password\Sender;
use RuntimeException;

/**
 * Send accessInfo (set-password link) without resetting the stored password.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/passwords.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-console-commands.md
 *
 * @noinspection PhpUnused
 */
class SendAccessInfoUser implements Command
{
    public function __construct(
        private EntityManager $entityManager,
        private RecoveryService $recovery,
        private Sender $sender,
    ) {}

    public function run(Params $params, IO $io): void
    {
        $userName = $params->getOption('userName');

        if ($userName === null || $userName === '') {
            throw new RuntimeException('Pass --userName=...');
        }

        /** @var ?User $user */
        $user = $this->entityManager
            ->getRDBRepositoryByClass(User::class)
            ->where([
                'userName' => $userName,
                'deleted' => false,
            ])
            ->findOne();

        if (!$user) {
            throw new RuntimeException('User not found: ' . $userName);
        }

        $email = $user->getEmailAddress();

        if (!$email) {
            throw new RuntimeException('User has no email: ' . $userName);
        }

        $request = $this->recovery->createRequestForNewUser($user);
        $this->sender->sendAccessInfo($user, $request);

        $io->writeLine('sent accessInfo to ' . $email);
        $io->writeLine('userId=' . $user->getId());
    }
}
