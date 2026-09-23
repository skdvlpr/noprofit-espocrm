<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Admission;

/**
 * When to build, replace, drop, or release the admission PDF.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
 */
class AdmissionPdfPlan
{
    public const SKIP_OPTION = 'skipAdmissionPdf';

    public static function shouldGenerate(
        bool $isAssociato,
        bool $isNew,
        bool $printedFieldsChanged,
        bool $hasFile,
    ): bool {
        if (!$isAssociato) {
            return false;
        }

        return $isNew || $printedFieldsChanged || !$hasFile;
    }

    public static function shouldRelease(
        bool $isConverted,
        bool $leadAssociato,
        bool $contactAssociato,
        bool $hasFile,
    ): bool {
        return $isConverted && $leadAssociato && $contactAssociato && $hasFile;
    }

    public static function shouldDrop(bool $isAssociato, bool $isConverted, bool $hasFile): bool
    {
        return !$isAssociato && !$isConverted && $hasFile;
    }
}
