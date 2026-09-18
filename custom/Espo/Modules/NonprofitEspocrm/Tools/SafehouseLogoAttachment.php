<?php

declare(strict_types=1);

namespace Espo\Modules\NonprofitEspocrm\Tools;

use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Core\Utils\Log;
use Espo\Entities\Attachment;
use Espo\ORM\EntityManager;

/**
 * Persist the Safe House mark once as an Inline Attachment so outbound
 * mail can use ?entryPoint=attachment&id=… (Espo converts that to CID).
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/attachments.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/template-custom-helper.md
 */
class SafehouseLogoAttachment
{
    public const ATTACHMENT_NAME = 'safehouse-email-logo.png';

    public const RELATIVE_PATH = 'client/custom/modules/nonprofit-espocrm/img/safe-house-logo.png';

    public function __construct(
        private EntityManager $entityManager,
        private Log $log,
        private FileStorageManager $fileStorage,
    ) {}

    public function pngPath(): string
    {
        return dirname(__DIR__, 5) . '/' . self::RELATIVE_PATH;
    }

    public function ensureId(): ?string
    {
        $existing = $this->entityManager
            ->getRDBRepository(Attachment::ENTITY_TYPE)
            ->where([
                'name' => self::ATTACHMENT_NAME,
                'role' => Attachment::ROLE_INLINE_ATTACHMENT,
                'deleted' => false,
            ])
            ->findOne();

        if ($existing instanceof Attachment && $this->fileStorage->exists($existing)) {
            return $existing->getId();
        }

        if ($existing instanceof Attachment) {
            $this->log->warning(
                'Safe House logo attachment {id} is missing on disk; recreating.',
                ['id' => $existing->getId()]
            );
            $this->entityManager->removeEntity($existing);
        }

        $path = $this->pngPath();

        if (!is_readable($path)) {
            $this->log->warning('Safe House logo file missing at {path}', ['path' => $path]);

            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false || $contents === '') {
            return null;
        }

        /** @var Attachment $attachment */
        $attachment = $this->entityManager->getRDBRepositoryByClass(Attachment::class)->getNew();
        $attachment
            ->setName(self::ATTACHMENT_NAME)
            ->setType('image/png')
            ->setRole(Attachment::ROLE_INLINE_ATTACHMENT)
            ->setSize(strlen($contents))
            ->setContents($contents);

        $this->entityManager->saveEntity($attachment);

        return $attachment->getId();
    }

    /**
     * Query string for `?entryPoint=attachment…id=`.
     *
     * Shift mail injects HTML after EmailTemplate Htmlizer
     * (`skipInlineAttachmentHandling` true) — use `$htmlizerSafe = false`
     * (`&amp;id=`). Access-info / password-change-link go through
     * Htmlizer with that flag **false**, so `&amp;` is rewritten to `&`
     * and then to a local file path. `$htmlizerSafe = true` emits
     * `&amp;amp;id=` so one Htmlizer pass leaves `&amp;id=` for
     * Email::getBodyForSending() CID rewrite.
     *
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/attachments.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/template-custom-helper.md
     */
    public function entryPointSrc(string $attachmentId, bool $htmlizerSafe = false): string
    {
        $id = htmlspecialchars($attachmentId, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $ampId = $htmlizerSafe ? '&amp;amp;id=' : '&amp;id=';

        return '?entryPoint=attachment' . $ampId . $id;
    }

    public function imgHtml(int $width = 160, bool $htmlizerSafe = false): string
    {
        $attachmentId = $this->ensureId();

        if ($attachmentId === null) {
            return '';
        }

        return '<img src="' . $this->entryPointSrc($attachmentId, $htmlizerSafe) . '"'
            . ' alt="Safe House" width="' . $width . '" '
            . 'style="display:block;max-width:' . $width . 'px;height:auto;border:0;outline:none;">';
    }
}
