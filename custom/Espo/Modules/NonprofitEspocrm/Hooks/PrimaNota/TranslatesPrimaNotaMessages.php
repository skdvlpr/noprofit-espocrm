<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\PrimaNota;

use Espo\Core\Utils\Language;

trait TranslatesPrimaNotaMessages
{
    private Language $language;

    private function msg(string $label): string
    {
        $translated = $this->language->translate($label, 'messages', 'PrimaNota');

        return is_string($translated) && $translated !== '' ? $translated : $label;
    }
}
