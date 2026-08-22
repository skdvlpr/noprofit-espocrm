<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\WorkflowEngine;

use Espo\Modules\NonprofitEspocrm\Tools\EmailTemplate\TemplatePlaceholderHelper;
use Espo\Modules\WorkflowEngine\Services\TemplateRenderer;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TemplateRendererTest extends TestCase
{
    private TemplateRenderer $renderer;

    protected function setUp(): void
    {
        $entityManager = $this->createMock(EntityManager::class);
        $log = $this->createMock(LoggerInterface::class);
        $placeholderHelper = $this->createMock(TemplatePlaceholderHelper::class);

        $placeholderHelper
            ->method('applyRecordUrls')
            ->willReturnArgument(0);

        $placeholderHelper
            ->method('clearUnresolvedEntityPlaceholders')
            ->willReturnArgument(0);

        $this->renderer = new TemplateRenderer($entityManager, $log, $placeholderHelper);
    }

    public function testNormalizeEmailTemplatePlaceholdersForEntityScope(): void
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('getEntityType')->willReturn('Task');

        $template = 'Hello {Task.name}, parent {Parent.status}, person {Person.emailAddress}';

        $this->assertSame(
            'Hello {{name}}, parent {{status}}, person {{emailAddress}}',
            $this->renderer->normalizeEmailTemplatePlaceholders($entity, $template)
        );
    }

    public function testNormalizeEmailTemplatePlaceholdersLeavesUnrelatedScopesAsHtmlizerTokens(): void
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('getEntityType')->willReturn('Task');

        $template = 'Account: {Account.name}';

        $this->assertSame(
            'Account: {{name}}',
            $this->renderer->normalizeEmailTemplatePlaceholders($entity, $template)
        );
    }

    public function testNormalizeEmailTemplatePlaceholdersNoOpWhenNoBraces(): void
    {
        $entity = $this->createMock(Entity::class);

        $this->assertSame('plain text', $this->renderer->normalizeEmailTemplatePlaceholders($entity, 'plain text'));
        $this->assertSame('', $this->renderer->normalizeEmailTemplatePlaceholders($entity, ''));
    }
}
