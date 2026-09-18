<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Core\Htmlizer\Helper\Data;
use Espo\Core\Utils\Log;
use Espo\Modules\NonprofitEspocrm\TemplateHelpers\SafehouseLogo;
use Espo\Modules\NonprofitEspocrm\Tools\SafehouseLogoAttachment;
use Espo\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

/**
 * Access-info logo is CID-ready (entryPoint=attachment), not a remote PNG URL.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/template-custom-helper.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/attachments.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/tests.md
 */
class SafehouseLogoHelperTest extends TestCase
{
    public function testEnsureIdChecksStoredFileExists(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 5)
            . '/custom/Espo/Modules/NonprofitEspocrm/Tools/SafehouseLogoAttachment.php'
        );

        $this->assertStringContainsString('fileStorage->exists', $src);
        $this->assertStringContainsString('missing on disk', $src);
    }

    public function testPngFileIsReadable(): void
    {
        $tool = new SafehouseLogoAttachment(
            $this->createMock(EntityManager::class),
            $this->createMock(Log::class),
            $this->createMock(FileStorageManager::class),
        );

        $this->assertFileIsReadable($tool->pngPath());
    }

    public function testHelperUsesInlineAttachmentSrc(): void
    {
        $logo = $this->createMock(SafehouseLogoAttachment::class);
        $logo->expects($this->once())
            ->method('imgHtml')
            ->with(160, true)
            ->willReturn(
                '<img src="?entryPoint=attachment&amp;amp;id=abc" alt="Safe House" width="160">'
            );

        $helper = new SafehouseLogo($logo);
        $html = (string) $helper->render($this->createMock(Data::class))->getValue();

        $this->assertStringContainsString('entryPoint=attachment', $html);
        $this->assertStringContainsString('id=abc', $html);
        $this->assertStringNotContainsString('safe-house-logo.png', $html);
        $this->assertStringNotContainsString('http', $html);
    }

    public function testShiftSrcKeepsSingleAmpForEmailCid(): void
    {
        $tool = new SafehouseLogoAttachment(
            $this->createMock(EntityManager::class),
            $this->createMock(Log::class),
            $this->createMock(FileStorageManager::class),
        );

        $src = $tool->entryPointSrc('abc123');

        $this->assertSame('?entryPoint=attachment&amp;id=abc123', $src);
    }

    public function testHtmlizerSafeSrcSurvivesPostProcessForEmailCid(): void
    {
        $tool = new SafehouseLogoAttachment(
            $this->createMock(EntityManager::class),
            $this->createMock(Log::class),
            $this->createMock(FileStorageManager::class),
        );

        $src = $tool->entryPointSrc('abc123', true);
        $this->assertSame('?entryPoint=attachment&amp;amp;id=abc123', $src);

        $afterHtmlizer = str_replace(
            '?entryPoint=attachment&amp;',
            '?entryPoint=attachment&',
            $src
        );

        $this->assertSame('?entryPoint=attachment&amp;id=abc123', $afterHtmlizer);
        $this->assertDoesNotMatchRegularExpression(
            '/\?entryPoint=attachment&id=/',
            $afterHtmlizer
        );

        $body = '<img src="' . $afterHtmlizer . '" alt="Safe House">';
        $forSending = str_replace(
            '"?entryPoint=attachment&amp;id=abc123"',
            '"cid:abc123@espo"',
            $body
        );

        $this->assertStringContainsString('cid:abc123@espo', $forSending);
        $this->assertStringNotContainsString('entryPoint=attachment', $forSending);
    }

    public function testHelperEmptyWhenAttachmentMissing(): void
    {
        $logo = $this->createMock(SafehouseLogoAttachment::class);
        $logo->method('imgHtml')->willReturn('');

        $helper = new SafehouseLogo($logo);

        $this->assertSame(
            '',
            (string) $helper->render($this->createMock(Data::class))->getValue()
        );
    }
}
