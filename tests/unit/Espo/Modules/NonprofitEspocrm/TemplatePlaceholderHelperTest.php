<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Core\Utils\Config;
use Espo\Modules\NonprofitEspocrm\Tools\EmailTemplate\TemplatePlaceholderHelper;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

class TemplatePlaceholderHelperTest extends TestCase
{
    private TemplatePlaceholderHelper $helper;

    protected function setUp(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->with('siteUrl')->willReturn('https://crm.example.org/');

        $this->helper = new TemplatePlaceholderHelper($config, $this->createMock(EntityManager::class));
    }

    public function testUrlForMissingEntityReturnsEmpty(): void
    {
        $this->assertSame('', $this->helper->urlFor(null));
    }

    public function testUrlForEntityWithoutIdReturnsEmpty(): void
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('hasId')->willReturn(false);

        $this->assertSame('', $this->helper->urlFor($entity));
    }

    public function testUrlForEntityWithIdBuildsDeepLink(): void
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('hasId')->willReturn(true);
        $entity->method('getEntityType')->willReturn('ActivityOffer');
        $entity->method('getId')->willReturn('abc123');

        $this->assertSame(
            'https://crm.example.org/#ActivityOffer/view/abc123',
            $this->helper->urlFor($entity)
        );
    }

    public function testApplyRecordUrlsReplacesPrimaryTokens(): void
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('hasId')->willReturn(true);
        $entity->method('getEntityType')->willReturn('ActivityOffer');
        $entity->method('getId')->willReturn('plan1');

        $text = 'Open {recordUrl} or {planUrl} or {ActivityOffer.recordUrl}';

        $this->assertSame(
            'Open https://crm.example.org/#ActivityOffer/view/plan1 or https://crm.example.org/#ActivityOffer/view/plan1 or https://crm.example.org/#ActivityOffer/view/plan1',
            $this->helper->applyRecordUrls($text, $entity)
        );
    }

    public function testApplyRecordUrlsClearsUnknownRecordUrlTokens(): void
    {
        $text = 'Missing {Account.recordUrl} and {recordUrl}';

        $this->assertSame('Missing  and ', $this->helper->applyRecordUrls($text));
    }

    public function testClearUnresolvedEntityPlaceholdersRemovesDottedTokens(): void
    {
        $text = 'Hi {User.firstName}, see {today} and {optOutUrl}';

        $this->assertSame('Hi , see {today} and {optOutUrl}', $this->helper->clearUnresolvedEntityPlaceholders($text));
    }

    public function testClearUnresolvedEntityPlaceholdersEmptyOrNoBracesIsNoOp(): void
    {
        $this->assertSame('', $this->helper->clearUnresolvedEntityPlaceholders(''));
        $this->assertSame('plain text', $this->helper->clearUnresolvedEntityPlaceholders('plain text'));
    }
}
