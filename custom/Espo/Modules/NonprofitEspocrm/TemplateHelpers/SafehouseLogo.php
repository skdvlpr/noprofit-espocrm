<?php

declare(strict_types=1);

namespace Espo\Modules\NonprofitEspocrm\TemplateHelpers;

use Espo\Core\Htmlizer\Helper;
use Espo\Core\Htmlizer\Helper\Data;
use Espo\Core\Htmlizer\Helper\Result;
use Espo\Modules\NonprofitEspocrm\Tools\SafehouseLogoAttachment;

/**
 * Safe House mark for system emails. Use Espo inline-attachment src so
 * send() rewrites it to cid:{id}@espo. Access-info Htmlizer rewrites
 * `&amp;` once — helper must emit `&amp;amp;id=` so CID still works in
 * Gmail. Shift mail uses imgHtml() without that flag.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/template-custom-helper.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-templates.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/attachments.md
 */
class SafehouseLogo implements Helper
{
    public function __construct(
        private SafehouseLogoAttachment $logoAttachment
    ) {}

    public function render(Data $data): Result
    {
        $html = $this->logoAttachment->imgHtml(160, true);

        if ($html === '') {
            return Result::createEmpty();
        }

        return Result::createSafeString($html);
    }
}
