<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Tools\Admission\NewsletterConsent;
use Espo\ORM\Entity;
use PHPUnit\Framework\TestCase;

/**
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/tests.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
 */
class NewsletterConsentTest extends TestCase
{
    public function testAcconsente(): void
    {
        $description = "Domanda di ammissione socio dal sito.\nNewsletter: acconsente";

        $this->assertSame(NewsletterConsent::YES, NewsletterConsent::fromDescription($description));
    }

    public function testNonAcconsente(): void
    {
        $this->assertSame(
            NewsletterConsent::NO,
            NewsletterConsent::fromDescription("Newsletter: non acconsente\n")
        );
    }

    public function testSiteLineFillsDropdownAndLeavesDescription(): void
    {
        $store = [
            'description' => "TEST\nNewsletter: acconsente",
            'newsletterConsent' => null,
        ];
        $entity = $this->createMock(Entity::class);
        $entity->method('get')->willReturnCallback(
            static fn (string $field): mixed => $store[$field] ?? null
        );
        $entity->method('set')->willReturnCallback(
            function (string $field, mixed $value) use (&$store, $entity): Entity {
                $store[$field] = $value;

                return $entity;
            }
        );

        NewsletterConsent::sync($entity);

        $this->assertSame('Yes', $store['newsletterConsent']);
        $this->assertSame('TEST', $store['description']);
    }

    public function testMissingLineIsEmpty(): void
    {
        $this->assertSame('', NewsletterConsent::fromDescription('Statuto accettato: sì.'));
        $this->assertSame('', NewsletterConsent::fromDescription(null));
    }
}
