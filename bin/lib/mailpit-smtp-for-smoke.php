<?php

declare(strict_types=1);

/**
 * Temporarily point the system Group Email Account SMTP at DDEV Mailpit for smokes.
 *
 * Interactive Espo mail must keep using the instance Group Email / Admin SMTP.
 * Smokes call arm/disarm around send assertions; never leave Mailpit on the account.
 *
 * @return array{account: ?\Espo\ORM\Entity, backup: ?array<string, mixed>}
 */
function safehouse_mailpit_smoke_arm(\Espo\ORM\EntityManager $em, \Espo\Core\Utils\Config $config): array
{
    $from = trim((string) ($config->get('outboundEmailFromAddress') ?: ''));

    if ($from === '') {
        return ['account' => null, 'backup' => null];
    }

    /** @var ?\Espo\ORM\Entity $account */
    $account = $em
        ->getRDBRepository('InboundEmail')
        ->where([
            'status' => 'Active',
            'useSmtp' => true,
            'emailAddress' => $from,
        ])
        ->findOne();

    if ($account === null) {
        // Case-insensitive fallback (Espo stores mixed case).
        foreach (
            $em->getRDBRepository('InboundEmail')
                ->where(['status' => 'Active', 'useSmtp' => true])
                ->find() as $candidate
        ) {
            if (strcasecmp((string) $candidate->get('emailAddress'), $from) === 0) {
                $account = $candidate;
                break;
            }
        }
    }

    if ($account === null) {
        return ['account' => null, 'backup' => null];
    }

    $backup = [
        'smtpHost' => $account->get('smtpHost'),
        'smtpPort' => $account->get('smtpPort'),
        'smtpAuth' => $account->get('smtpAuth'),
        'smtpSecurity' => $account->get('smtpSecurity'),
        'smtpUsername' => $account->get('smtpUsername'),
        'smtpPassword' => $account->get('smtpPassword'),
        'smtpAuthMechanism' => $account->get('smtpAuthMechanism'),
    ];

    $account->set([
        'smtpHost' => '127.0.0.1',
        'smtpPort' => 1025,
        'smtpAuth' => false,
        'smtpSecurity' => null,
        'smtpUsername' => null,
        'smtpPassword' => null,
    ]);
    $em->saveEntity($account);

    return ['account' => $account, 'backup' => $backup];
}

/**
 * @param array{account: ?\Espo\ORM\Entity, backup: ?array<string, mixed>} $armed
 */
function safehouse_mailpit_smoke_disarm(\Espo\ORM\EntityManager $em, array $armed): void
{
    $account = $armed['account'] ?? null;
    $backup = $armed['backup'] ?? null;

    if ($account === null || $backup === null) {
        return;
    }

    /** @var \Espo\ORM\Entity $fresh */
    $fresh = $em->getEntityById('InboundEmail', $account->getId()) ?? $account;
    $fresh->set($backup);
    $em->saveEntity($fresh);
}
