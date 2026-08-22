<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Tools\WebPush\WebPushPreferenceChecker;
use Espo\ORM\Entity;
use PHPUnit\Framework\TestCase;

class WebPushPreferenceCheckerTest extends TestCase
{
    private WebPushPreferenceChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new WebPushPreferenceChecker();
    }

    public function testDisabledWebPushBlocksAllEntities(): void
    {
        $preferences = $this->preferencesWith(false, []);

        $this->assertFalse($this->checker->allowsEntity($preferences, 'Task'));
        $this->assertFalse($this->checker->allowsEntity($preferences, null));
    }

    public function testEnabledWebPushAllowsEntityNotInIgnoreList(): void
    {
        $preferences = $this->preferencesWith(true, ['Meeting']);

        $this->assertTrue($this->checker->allowsEntity($preferences, 'Task'));
    }

    public function testEnabledWebPushBlocksIgnoredEntityType(): void
    {
        $preferences = $this->preferencesWith(true, ['Task', 'Meeting']);

        $this->assertFalse($this->checker->allowsEntity($preferences, 'Task'));
        $this->assertTrue($this->checker->allowsEntity($preferences, 'Call'));
    }

    public function testEnabledWebPushAllowsWhenEntityTypeEmpty(): void
    {
        $preferences = $this->preferencesWith(true, ['Task']);

        $this->assertTrue($this->checker->allowsEntity($preferences, null));
        $this->assertTrue($this->checker->allowsEntity($preferences, ''));
    }

    public function testNonArrayIgnoreListAllowsAll(): void
    {
        $preferences = $this->preferencesWith(true, 'not-an-array');

        $this->assertTrue($this->checker->allowsEntity($preferences, 'Task'));
    }

    /**
     * @param mixed $ignoreList
     */
    private function preferencesWith(bool $enabled, mixed $ignoreList): Entity
    {
        $preferences = $this->createMock(Entity::class);
        $preferences->method('get')->willReturnMap([
            ['webPushEnabled', $enabled],
            ['assignmentPushNotificationsIgnoreEntityTypeList', $ignoreList],
        ]);

        return $preferences;
    }
}
