<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\FoodParcel;

use Espo\Core\Acl;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Utils\Config;
use Espo\Entities\Template;
use Espo\ORM\EntityManager;
use Espo\Tools\Pdf\Data;
use Espo\Tools\Pdf\Params;
use Espo\Tools\Pdf\Service as PdfService;
use RuntimeException;

class FoodParcelPdfService
{
    public const TEMPLATE_NAME = 'Food Parcel Registration — Safehouse';

    private const TEMPLATE_BODY_PATH = __DIR__ . '/../../Resources/templates/FoodParcelRegistration/pdfBody.html';

    private const LOGO_TAG_PLACEHOLDER = '{{LOGO_TAG}}';

    public function __construct(
        private EntityManager $entityManager,
        private PdfService $pdfService,
        private Acl $acl,
        private Config $config,
        private FoodParcelRegistrationSnapshot $foodParcelRegistrationSnapshot,
    ) {}

    /**
     * @return array{contents: string, contentType: string}
     */
    public function generateForRecord(string $id): array
    {
        $this->provisionTemplate();

        $template = $this->entityManager
            ->getRDBRepository(Template::ENTITY_TYPE)
            ->where([
                'entityType' => 'FoodParcelRegistration',
                'name' => self::TEMPLATE_NAME,
            ])
            ->findOne();

        if (!$template) {
            throw new NotFound('PDF template not found.');
        }

        $registration = $this->entityManager->getEntityById('FoodParcelRegistration', $id);

        if ($registration === null) {
            throw new NotFound();
        }

        $before = $this->foodParcelRegistrationSnapshot->collect($registration);
        $this->foodParcelRegistrationSnapshot->apply($registration);

        if ($this->foodParcelRegistrationSnapshot->hasChanges($registration, $before)) {
            $this->entityManager->saveEntity($registration);
        }

        $result = $this->pdfService->generate(
            'FoodParcelRegistration',
            $id,
            $template->getId(),
            Params::create()->withAcl(),
            Data::create(),
        );

        return [
            'contents' => $result->getString(),
            'contentType' => 'application/pdf',
        ];
    }

    public function provisionTemplate(): void
    {
        $body = $this->buildTemplateBody();

        $existing = $this->entityManager
            ->getRDBRepository(Template::ENTITY_TYPE)
            ->where([
                'entityType' => 'FoodParcelRegistration',
                'name' => self::TEMPLATE_NAME,
            ])
            ->findOne();

        if ($existing && $existing->get('body') === $body) {
            return;
        }

        $payload = [
            'body' => $body,
            'pageFormat' => 'A4',
            'pageOrientation' => 'Portrait',
            'fontFace' => 'DejaVu Sans',
        ];

        if ($existing) {
            $existing->set($payload);
            $this->entityManager->saveEntity($existing);

            return;
        }

        $template = $this->entityManager->getNewEntity(Template::ENTITY_TYPE);
        $template->set([
            'name' => self::TEMPLATE_NAME,
            'entityType' => 'FoodParcelRegistration',
            ...$payload,
        ]);

        $this->entityManager->saveEntity($template);
    }

    private function buildTemplateBody(): string
    {
        if (!is_readable(self::TEMPLATE_BODY_PATH)) {
            throw new RuntimeException('Food parcel PDF template file is missing.');
        }

        $contents = file_get_contents(self::TEMPLATE_BODY_PATH);

        if ($contents === false || $contents === '') {
            throw new RuntimeException('Food parcel PDF template file is empty.');
        }

        return str_replace(self::LOGO_TAG_PLACEHOLDER, $this->buildLogoImgTag(), $contents);
    }

    private function buildLogoImgTag(): string
    {
        $paths = [
            dirname(__DIR__, 2) . '/Resources/branding/logo.png',
            dirname(__DIR__, 6) . '/client/img/logo.png',
        ];

        foreach ($paths as $path) {
            if (!is_readable($path)) {
                continue;
            }

            $contents = file_get_contents($path);

            if ($contents === false || $contents === '') {
                continue;
            }

            $encoded = base64_encode($contents);

            return '<img src="@' . $encoded . '" height="56" alt="Safe House">';
        }

        return '<strong>Safe House</strong>';
    }
}
