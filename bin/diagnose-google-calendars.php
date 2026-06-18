<?php

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Core\InjectableFactory;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\GoogleIntegration\Core\ExternalAccount\Clients\Google;
use Espo\Modules\GoogleIntegration\Tools\Calendar\Api\GoogleClientProvider;
use Espo\Modules\GoogleIntegration\Tools\Installer;

try {
    $app = new Application();
    $container = $app->getContainer();
    $em = $container->getByClass(EntityManager::class);

    $userName = $argv[1] ?? 'admin';
    $user = $em->getRepository('User')->where(['userName' => $userName])->findOne();

    if (!$user) {
        fwrite(STDERR, "User not found: {$userName}\n");
        exit(1);
    }

    $container->getByClass(ApplicationUser::class)->setUser($user);

    echo "User: {$user->get('userName')} ({$user->getId()})\n";

    $externalAccountId = Installer::INTEGRATION_ID . '__' . $user->getId();
    $externalAccount = $em->getEntityById('ExternalAccount', $externalAccountId);

    if ($externalAccount) {
        echo 'ExternalAccount enabled: ' . ($externalAccount->get('enabled') ? 'yes' : 'no') . "\n";
        $data = $externalAccount->get('data');
        echo 'Has access token: ' . (!empty($data->accessToken) ? 'yes' : 'no') . "\n";
        echo 'Has refresh token: ' . (!empty($data->refreshToken) ? 'yes' : 'no') . "\n";
    } else {
        echo "No ExternalAccount GoogleCalendarDrive\n";
    }

    $factory = $container->getByClass(InjectableFactory::class);
    $provider = $factory->create(GoogleClientProvider::class);
    $client = $provider->get();
    $list = $client->listCalendars();
    echo 'listCalendars count=' . count($list) . "\n";

    foreach ($list as $item) {
        if (!is_array($item)) {
            continue;
        }

        echo '  - ' . ($item['summary'] ?? '?') . ' [' . ($item['id'] ?? '') . ']' . "\n";
    }
} catch (\Throwable $e) {
    echo 'error: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    exit(1);
}
