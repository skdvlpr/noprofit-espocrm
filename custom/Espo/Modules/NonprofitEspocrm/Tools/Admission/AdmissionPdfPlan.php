<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Admission;

/**
 * When to wipe a leftover stored admission file. Generation on save is
 * never requested: Print to PDF is on demand at GET.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/formula/ext.md
 */
class AdmissionPdfPlan
{
    public const SKIP_OPTION = 'skipAdmissionPdf';

    /**
     * Never store an admission PDF on save. Formula ext\pdf\generate
     * always writes an attachment; that path is rejected.
     */
    public static function shouldGenerate(
        bool $isAssociato,
        bool $isNew,
        bool $printedFieldsChanged,
        bool $hasFile,
    ): bool {
        return false;
    }

    public static function hasStoredFile(mixed $fileId): bool
    {
        return is_string($fileId) && $fileId !== '';
    }

    public static function shouldClearOnConvert(
        bool $isConverted,
        bool $leadAssociato,
        bool $contactAssociato,
        bool $leadHasFile,
        bool $contactHasFile,
    ): bool {
        if (!$isConverted || !$leadAssociato || !$contactAssociato) {
            return false;
        }

        return $leadHasFile || $contactHasFile;
    }

    public static function shouldDrop(bool $isAssociato, bool $isConverted, bool $hasFile): bool
    {
        return !$isAssociato && !$isConverted && $hasFile;
    }
}
