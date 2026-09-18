<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use PHPUnit\Framework\TestCase;

/**
 * Contact-first User POST is JS-only. Assert the payload builders strip password.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/passwords.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
 */
class CreateCrmUserPayloadOmitPasswordTest extends TestCase
{
    public function testEditViewDeletesPasswordFromUserPost(): void
    {
        $js = $this->readCustomJs('views/contact/record/edit.js');

        $this->assertStringContainsString('buildUserPostPayload()', $js);
        $this->assertStringContainsString('delete payload.password;', $js);
        $this->assertStringContainsString('delete payload.passwordConfirm;', $js);
        $this->assertStringContainsString('delete payload.generatePassword;', $js);
    }

    public function testReviewModalStashDeletesPassword(): void
    {
        $js = $this->readCustomJs('views/modals/create-crm-user.js');

        $this->assertStringContainsString('delete attributes.password;', $js);
        $this->assertStringContainsString('delete attributes.passwordConfirm;', $js);
        $this->assertStringContainsString('MUST NOT POST User', $js);
    }

    public function testCreateFromContactHidesPasswordFields(): void
    {
        $js = $this->readCustomJs('views/user/record/create-from-contact.js');

        $this->assertStringContainsString("this.hideField('password');", $js);
        $this->assertStringContainsString("this.hideField('generatePassword');", $js);
        $this->assertStringContainsString('sendPasswordCreateLink', $js);
    }

    private function readCustomJs(string $relative): string
    {
        $path = dirname(__DIR__, 5) . '/client/custom/modules/nonprofit-espocrm/src/' . $relative;
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, $path);

        return $contents;
    }
}
