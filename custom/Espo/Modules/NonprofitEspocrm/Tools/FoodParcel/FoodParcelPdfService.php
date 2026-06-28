<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\FoodParcel;

use Espo\Core\Acl;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Utils\Config;
use Espo\Entities\Template;
use Espo\ORM\EntityManager;
use Espo\Tools\Pdf\Data;
use Espo\Tools\Pdf\Params;
use Espo\Tools\Pdf\Service as PdfService;

class FoodParcelPdfService
{
    public const TEMPLATE_NAME = 'Food Parcel Registration — Safehouse';

    public function __construct(
        private EntityManager $entityManager,
        private PdfService $pdfService,
        private Acl $acl,
        private Config $config,
    ) {}

    /**
     * @return array{contents: string, contentType: string}
     */
    public function generateForRecord(string $id): array
    {
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
        $existing = $this->entityManager
            ->getRDBRepository(Template::ENTITY_TYPE)
            ->where([
                'entityType' => 'FoodParcelRegistration',
                'name' => self::TEMPLATE_NAME,
            ])
            ->findOne();

        if ($existing) {
            return;
        }

        $siteUrl = rtrim((string) $this->config->get('siteUrl'), '/');
        $logoUrl = $siteUrl . '/client/img/logo.svg';

        $body = <<<HTML
<div style="font-family: DejaVu Sans, sans-serif; font-size: 11pt;">
  <div style="background:#c0392b;color:#fff;padding:12px 16px;margin-bottom:20px;">
    <table width="100%"><tr>
      <td><strong>MODULO DI REGISTRAZIONE</strong><br><em>Pacco Spesa</em></td>
      <td align="right"><img src="{$logoUrl}" height="48" alt="Safe House"></td>
    </tr></table>
  </div>
  <p><strong>Nome / Cognome:</strong> {contactName}</p>
  <p><strong>Cod. Fisc.:</strong> {taxCode}</p>
  <p><strong>Nato a:</strong> {birthPlace}</p>
  <p><strong>Via/cso:</strong> {addressStreet} &nbsp; <strong>N°</strong> &nbsp; <strong>CAP</strong> {addressPostalCode}</p>
  <p><strong>Nucleo Famigliare:</strong> {household}</p>
  <p><strong>Telefono:</strong> {phone}</p>
  <p><strong>NOTE:</strong><br>{notes}</p>
  <h4>Date log (entrata | uscita)</h4>
  <pre>{dateLogsText}</pre>
</div>
HTML;

        $template = $this->entityManager->getNewEntity(Template::ENTITY_TYPE);
        $template->set([
            'name' => self::TEMPLATE_NAME,
            'entityType' => 'FoodParcelRegistration',
            'body' => $body,
            'pageFormat' => 'A4',
            'pageOrientation' => 'Portrait',
            'fontFace' => 'DejaVu Sans',
        ]);

        $this->entityManager->saveEntity($template);
    }
}
