<?php

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\Core\InjectableFactory;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DateSourceProvider;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventPusher;
use Espo\ORM\EntityManager;

$app = new Application();
$container = $app->getContainer();
$em = $container->getByClass(EntityManager::class);
$factory = $container->getByClass(InjectableFactory::class);

$admin = $em->getRDBRepository('User')->where(['userName' => 'admin', 'deleted' => false])->findOne();
$container->getByClass(ApplicationUser::class)->setUser($admin);

$pusher = $factory->create(EventPusher::class);
$provider = $factory->create(DateSourceProvider::class);
$method = new ReflectionMethod($pusher, 'buildSummary');
$method->setAccessible(true);

$fail = 0;

foreach (['Meeting' => '6a2b1019272073dfc', 'Call' => '6a2b101770941d9fe'] as $type => $id) {
    $entity = $em->getEntityById($type, $id);

    if ($entity === null) {
        echo "[FAIL] {$type} {$id} not found\n";
        $fail++;
        continue;
    }

    $sources = $provider->getActiveSourcesForEntityType($type);
    $source = $sources[0] ?? [];
    $title = $method->invoke($pusher, $entity, 'main', $source);
    $name = (string) $entity->get('name');
    $ok = $title === $name && !str_contains($title, ' - ');

    echo ($ok ? '[PASS]' : '[FAIL]') . " {$type} title=\"{$title}\"\n";
    echo "       dateStart={$entity->get('dateStart')} dateEnd={$entity->get('dateEnd')}\n";

    if (!$ok) {
        $fail++;
    }
}

exit($fail === 0 ? 0 : 1);
