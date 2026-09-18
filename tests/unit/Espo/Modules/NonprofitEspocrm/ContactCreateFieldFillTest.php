<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use PHPUnit\Framework\TestCase;

/**
 * Field-fill scenarios for Contact create → User review (004.2 UAT repair).
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/terms-and-naming.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/layout-manager.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/tests.md
 */
class ContactCreateFieldFillTest extends TestCase
{
    public function testEditLayoutKeepsPersonNameWithoutDuplicateFirstLast(): void
    {
        $path = $this->projectRoot() . '/custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/Contact/edit.json';
        $json = file_get_contents($path);
        $this->assertNotFalse($json);

        $layout = json_decode($json, true);
        $this->assertIsArray($layout);

        $names = [];

        foreach ($layout as $panel) {
            foreach ($panel['rows'] ?? [] as $row) {
                foreach ($row as $cell) {
                    if (is_array($cell) && isset($cell['name'])) {
                        $names[] = $cell['name'];
                    }
                }
            }
        }

        $this->assertContains('name', $names);
        $this->assertNotContains('firstName', $names);
        $this->assertNotContains('lastName', $names);
    }

    public function testCopiedIdentityIncludesSalutationAndOccasionalPreview(): void
    {
        $js = $this->readCustomJs('views/contact/record/edit.js');

        $this->assertStringContainsString('copiedIdentityFromContact()', $js);
        $this->assertStringContainsString("salutationName: this.model.get('salutationName')", $js);
        $this->assertStringContainsString("'isOccasional'", $js);
        $this->assertStringContainsString("'activityCompetences'", $js);
        $this->assertStringContainsString("'startDate'", $js);
    }

    public function testCreateFromContactLocksSalutationName(): void
    {
        $js = $this->readCustomJs('views/user/record/create-from-contact.js');

        $this->assertStringContainsString("'salutationName'", $js);
        $this->assertStringContainsString('lockCopiedIdentityFields', $js);
    }

    public function testUserPostStillOmitsPassword(): void
    {
        $js = $this->readCustomJs('views/contact/record/edit.js');

        $this->assertStringContainsString('delete payload.password;', $js);
        $this->assertStringContainsString('delete payload.generatePassword;', $js);
    }

    private function readCustomJs(string $relative): string
    {
        $path = $this->projectRoot() . '/client/custom/modules/nonprofit-espocrm/src/' . $relative;
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, $path);

        return $contents;
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 5);
    }
}
