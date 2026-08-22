<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\BugTracker;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Entities\Attachment;
use Espo\Modules\BugTracker\Tools\BugReportMailer;
use integration\Core\NoTransaction;
use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * BugTracker hooks, mailer, and screenshot cleanup.
 */
class BugTrackerTest extends SafehouseBaseTestCase
{
    public function testBugReportScopeAndSettingsFields(): void
    {
        if (!class_exists(\Espo\Modules\BugTracker\Tools\Installer::class)) {
            $this->markTestSkipped('BugTracker module not installed.');
        }

        $metadata = $this->getMetadata();

        $this->assertSame('BugTracker', $metadata->get(['scopes', 'BugReport', 'module']));
        $this->assertTrue((bool) $metadata->get(['scopes', 'BugReport', 'entity']));
        $this->assertSame(
            'attachmentMultiple',
            $metadata->get(['entityDefs', 'BugReport', 'fields', 'screenshots', 'type'])
        );
        $this->assertTrue(
            (bool) $metadata->get(['entityDefs', 'Settings', 'fields', 'bugTrackerEnabled'])
        );
        $this->assertTrue(
            (bool) $metadata->get(['entityDefs', 'Settings', 'fields', 'bugTrackerTechnicianEmail'])
        );
    }

    #[NoTransaction]
    public function testBeforeSavePrepareAutoNamesBugReport(): void
    {
        if (!class_exists(\Espo\Modules\BugTracker\Tools\Installer::class)) {
            $this->markTestSkipped('BugTracker module not installed.');
        }

        $em = $this->getEntityManager();

        $bug = $em->getNewEntity('BugReport');
        $bug->set([
            'description' => 'PHPUnit auto-name check',
            'pageUrl' => 'https://example.test/page',
            'pageTitle' => 'Example page',
            'status' => 'New',
        ]);
        $em->saveEntity($bug);

        $name = (string) $bug->get('name');

        $this->assertMatchesRegularExpression(
            '/^\d{8}-\d{4}-BugReport-[a-f0-9]{8}$/',
            $name
        );
    }

    #[NoTransaction]
    public function testBeforeSavePrepareBlocksCreateWhenDisabled(): void
    {
        if (!class_exists(\Espo\Modules\BugTracker\Tools\Installer::class)) {
            $this->markTestSkipped('BugTracker module not installed.');
        }

        $factory = $this->getContainer()->getByClass(InjectableFactory::class);
        /** @var ConfigWriter $configWriter */
        $configWriter = $factory->create(ConfigWriter::class);
        $configWriter->set('bugTrackerEnabled', false);
        $configWriter->save();
        $this->getConfig()->update();

        try {
            $em = $this->getEntityManager();
            $bug = $em->getNewEntity('BugReport');
            $bug->set([
                'description' => 'Should be blocked',
                'pageUrl' => 'https://example.test/blocked',
                'pageTitle' => 'Blocked',
                'status' => 'New',
            ]);
            $em->saveEntity($bug);

            $this->fail('Expected Forbidden when bug tracker is disabled.');
        } catch (Forbidden $e) {
            $this->assertStringContainsString('disabled', strtolower($e->getMessage()));
        } finally {
            $configWriter->set('bugTrackerEnabled', true);
            $configWriter->save();
            $this->getConfig()->update();
        }
    }

    #[NoTransaction]
    public function testAfterSaveCleanupScreenshotsRemovesAttachmentsOnClose(): void
    {
        if (!class_exists(\Espo\Modules\BugTracker\Tools\Installer::class)) {
            $this->markTestSkipped('BugTracker module not installed.');
        }

        $em = $this->getEntityManager();

        $bug = $em->getNewEntity('BugReport');
        $bug->set([
            'description' => 'Screenshot cleanup test',
            'pageUrl' => 'https://example.test/shots',
            'pageTitle' => 'Shots',
            'status' => 'New',
        ]);
        $em->saveEntity($bug);

        $attachment = $em->getNewEntity(Attachment::ENTITY_TYPE);
        $attachment->set([
            'name' => 'phpunit-screenshot.png',
            'type' => 'image/png',
            'size' => 128,
            'role' => 'Attachment',
            'field' => 'screenshots',
            'parentType' => 'BugReport',
            'parentId' => $bug->getId(),
        ]);
        $em->saveEntity($attachment);

        $bug->set('screenshotsIds', [$attachment->getId()]);
        $em->saveEntity($bug);

        $bug->set('status', 'Closed');
        $em->saveEntity($bug);

        $this->assertSame('Closed', $bug->get('status'));
        $this->assertNull($em->getEntityById(Attachment::ENTITY_TYPE, $attachment->getId()));
        $this->assertNotEmpty($bug->get('screenshotsClearedAt'));
    }

    #[NoTransaction]
    public function testAfterRemoveCleanupScreenshotsRemovesLinkedAttachments(): void
    {
        if (!class_exists(\Espo\Modules\BugTracker\Tools\Installer::class)) {
            $this->markTestSkipped('BugTracker module not installed.');
        }

        $em = $this->getEntityManager();

        $bug = $em->getNewEntity('BugReport');
        $bug->set([
            'description' => 'Remove cleanup test',
            'pageUrl' => 'https://example.test/remove',
            'pageTitle' => 'Remove',
            'status' => 'New',
        ]);
        $em->saveEntity($bug);

        $attachment = $em->getNewEntity(Attachment::ENTITY_TYPE);
        $attachment->set([
            'name' => 'phpunit-remove.png',
            'type' => 'image/png',
            'size' => 64,
            'role' => 'Attachment',
            'field' => 'screenshots',
            'parentType' => 'BugReport',
            'parentId' => $bug->getId(),
        ]);
        $em->saveEntity($attachment);

        $attachmentId = $attachment->getId();
        $bugId = $bug->getId();

        $em->removeEntity($bug);

        $this->assertNull($em->getEntityById('BugReport', $bugId));
        $this->assertNull($em->getEntityById(Attachment::ENTITY_TYPE, $attachmentId));
    }

    #[NoTransaction]
    public function testBugReportMailerResolvesAndRunsCreateCloseFlow(): void
    {
        if (!class_exists(\Espo\Modules\BugTracker\Tools\Installer::class)) {
            $this->markTestSkipped('BugTracker module not installed.');
        }

        $factory = $this->getContainer()->getByClass(InjectableFactory::class);
        /** @var BugReportMailer $mailer */
        $mailer = $factory->create(BugReportMailer::class);

        $config = $this->getConfig();
        $config->update();

        $this->assertTrue((bool) $config->get('bugTrackerEnabled'));

        $em = $this->getEntityManager();
        $bug = $em->getNewEntity('BugReport');
        $bug->set([
            'description' => 'Mailer flow test',
            'pageUrl' => 'https://example.test/mailer',
            'pageTitle' => 'Mailer',
            'status' => 'New',
        ]);
        $em->saveEntity($bug);

        $mailer->notifyTechnicianNewReport($bug);

        $bug->set('status', 'Closed');
        $em->saveEntity($bug);

        $mailer->notifyReporterClosed($bug);

        $this->assertSame('Closed', $bug->get('status'));
    }
}
