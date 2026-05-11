<?php

namespace Espo\Modules\SafehouseCrm\Classes\FieldValidators\AccountWebsite\Name;

use Espo\Core\FieldValidation\Validator;
use Espo\Core\FieldValidation\Validator\Data;
use Espo\Core\FieldValidation\Validator\Failure;
use Espo\ORM\Entity;

/**
 * @implements Validator<Entity>
 */
class HttpUrl implements Validator
{
    private const ALLOWED_SCHEME_LIST = ['http', 'https'];

    public function validate(Entity $entity, string $field, Data $data): ?Failure
    {
        $value = $entity->get($field);

        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            return Failure::create();
        }

        $value = trim($value);

        if ($value === '') {
            return Failure::create();
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);

        if ($scheme !== null && $scheme !== false) {
            if (!in_array(strtolower($scheme), self::ALLOWED_SCHEME_LIST, true)) {
                return Failure::create();
            }

            $url = $value;
        } else {
            $url = 'https://' . $value;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return Failure::create();
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (!is_string($host) || $host === '' || !str_contains($host, '.')) {
            return Failure::create();
        }

        return null;
    }
}
