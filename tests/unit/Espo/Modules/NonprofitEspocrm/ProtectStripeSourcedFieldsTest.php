<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Entities\User;
use Espo\Modules\NonprofitEspocrm\Hooks\PrimaNota\ProtectStripeSourcedFields;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ProtectStripeSourcedFieldsTest extends TestCase
{
    public function testIncompleteInteractiveMoneyEditIsBlocked(): void
    {
        $hook = $this->hook($this->interactiveUser());
        $entity = $this->stripeEntity(
            fetched: ['donationPaymentProvider' => 'Stripe', 'stripeChargeId' => ''],
            changed: ['amountGross' => 9999.0],
        );

        $this->expectException(BadRequest::class);
        $hook->beforeSave($entity, SaveOptions::fromAssoc([]));
    }

    public function testIncompleteInteractivePaymentStatusEditIsBlocked(): void
    {
        $hook = $this->hook($this->interactiveUser());
        $entity = $this->stripeEntity(
            fetched: ['donationPaymentProvider' => 'Stripe', 'stripeChargeId' => null],
            changed: ['paymentStatus' => 'Inviato'],
        );

        $this->expectException(BadRequest::class);
        $hook->beforeSave($entity, SaveOptions::fromAssoc([]));
    }

    public function testIncompleteSystemActorMaySettle(): void
    {
        $hook = $this->hook($this->systemUser());
        $entity = $this->stripeEntity(
            fetched: ['donationPaymentProvider' => 'Stripe', 'stripeChargeId' => ''],
            changed: [
                'commissionAmount' => 0.33,
                'stripeChargeId' => 'ch_backfill',
            ],
        );

        $hook->beforeSave($entity, SaveOptions::fromAssoc([]));
        $this->assertTrue(true);
    }

    public function testIncompleteApiActorMaySettle(): void
    {
        $hook = $this->hook($this->apiUser());
        $entity = $this->stripeEntity(
            fetched: ['donationPaymentProvider' => 'Stripe', 'stripeChargeId' => ''],
            changed: ['amount' => 4.67, 'stripeChargeId' => 'ch_api'],
        );

        $hook->beforeSave($entity, SaveOptions::fromAssoc([]));
        $this->assertTrue(true);
    }

    public function testGapFillDoesNotAllowEmptyToValueMoneyWrite(): void
    {
        $hook = $this->hook($this->interactiveUser());
        $entity = $this->stripeEntity(
            fetched: [
                'donationPaymentProvider' => 'Stripe',
                'stripeChargeId' => 'ch_set',
                'amountGross' => null,
            ],
            changed: ['amountGross' => 42.0],
        );

        $this->expectException(BadRequest::class);
        $hook->beforeSave($entity, SaveOptions::fromAssoc([]));
    }

    public function testGapFillAllowsEmptyToValueReceiptEmail(): void
    {
        $hook = $this->hook($this->interactiveUser());
        $entity = $this->stripeEntity(
            fetched: [
                'donationPaymentProvider' => 'Stripe',
                'stripeChargeId' => 'ch_set',
                'stripeReceiptEmail' => null,
            ],
            changed: ['stripeReceiptEmail' => 'donor@example.org'],
        );

        $hook->beforeSave($entity, SaveOptions::fromAssoc([]));
        $this->assertTrue(true);
    }

    public function testNoGapFillAttributesIncludeMoneyAndPaymentStatus(): void
    {
        $reflection = new ReflectionClass(ProtectStripeSourcedFields::class);
        $constant = $reflection->getConstant('NO_GAP_FILL_ATTRIBUTES');

        $this->assertIsArray($constant);
        $this->assertContains('amountGross', $constant);
        $this->assertContains('amount', $constant);
        $this->assertContains('paymentStatus', $constant);
        $this->assertContains('stripeChargeId', $constant);
        $this->assertNotContains('stripeReceiptEmail', $constant);
    }

    private function hook(User $user): ProtectStripeSourcedFields
    {
        $language = $this->createMock(Language::class);
        $language->method('translate')->willReturn('stripeSourcedReadOnly');

        $config = $this->createMock(Config::class);
        $config->method('get')->willReturn([]);

        return new ProtectStripeSourcedFields($language, $user, $config);
    }

    private function interactiveUser(): User
    {
        $user = $this->createMock(User::class);
        $user->method('isApi')->willReturn(false);
        $user->method('isSystem')->willReturn(false);
        $user->method('get')->willReturn('admin');
        $user->method('getId')->willReturn('1');

        return $user;
    }

    private function systemUser(): User
    {
        $user = $this->createMock(User::class);
        $user->method('isApi')->willReturn(false);
        $user->method('isSystem')->willReturn(true);
        $user->method('get')->willReturn('system');
        $user->method('getId')->willReturn('system');

        return $user;
    }

    private function apiUser(): User
    {
        $user = $this->createMock(User::class);
        $user->method('isApi')->willReturn(true);
        $user->method('isSystem')->willReturn(false);
        $user->method('get')->willReturn('website-api');
        $user->method('getId')->willReturn('api-1');

        return $user;
    }

    /**
     * @param array<string, mixed> $fetched
     * @param array<string, mixed> $changed
     */
    private function stripeEntity(array $fetched, array $changed): Entity
    {
        $current = array_merge($fetched, $changed);

        $entity = $this->createMock(Entity::class);
        $entity->method('isNew')->willReturn(false);
        $entity->method('isAttributeChanged')->willReturnCallback(
            static fn (string $attribute): bool => array_key_exists($attribute, $changed)
        );
        $entity->method('getFetched')->willReturnCallback(
            static fn (string $attribute): mixed => $fetched[$attribute] ?? null
        );
        $entity->method('get')->willReturnCallback(
            static fn (string $attribute): mixed => $current[$attribute] ?? null
        );

        return $entity;
    }
}
