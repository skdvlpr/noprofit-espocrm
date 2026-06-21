<?php

namespace Espo\Modules\SafehouseCrm\Tools\Reporting\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Mail\Exceptions\SendingError;
use Espo\Modules\SafehouseCrm\Tools\Reporting\ReportingEmailExporter;

/**
 * Email reporting-entity export with current list filters (Task 7.3.6).
 */
class PostReportingEmailExport implements Action
{
    public function __construct(
        private ReportingEmailExporter $emailExporter,
    ) {}

    public function process(Request $request): Response
    {
        $data = $request->getParsedBody() ?? [];

        if (!is_array($data)) {
            throw new BadRequest();
        }

        try {
            $this->emailExporter->send($data);
        } catch (Forbidden $e) {
            throw $e;
        } catch (SendingError $e) {
            throw new BadRequest($e->getMessage());
        }

        return ResponseComposer::json([
            'success' => true,
        ]);
    }
}
