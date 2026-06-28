<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\FoodParcel\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\NonprofitEspocrm\Tools\FoodParcel\FoodParcelPdfService;

class GetFoodParcelRegistrationPdf implements Action
{
    public function __construct(
        private FoodParcelPdfService $foodParcelPdfService,
    ) {}

    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id');

        if (!$id) {
            throw new BadRequest();
        }

        $result = $this->foodParcelPdfService->generateForRecord($id);

        return ResponseComposer::empty()
            ->setHeader('Content-Type', $result['contentType'])
            ->setHeader('Content-Disposition', 'inline; filename="food-parcel-registration.pdf"')
            ->writeBody($result['contents']);
    }
}
