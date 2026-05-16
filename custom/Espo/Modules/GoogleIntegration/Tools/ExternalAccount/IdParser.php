<?php

namespace Espo\Modules\GoogleIntegration\Tools\ExternalAccount;

use Espo\Core\Exceptions\BadRequest;

/**
 * Parses ExternalAccount ids ({integration}__{userId}).
 */
final class IdParser
{
    /**
     * @return array{integration: string, userId: string}
     * @throws BadRequest
     */
    public static function parse(string $id): array
    {
        $parts = explode('__', $id, 2);

        if (
            count($parts) !== 2
            || $parts[0] === ''
            || $parts[1] === ''
        ) {
            throw new BadRequest('Invalid external account id.');
        }

        return [
            'integration' => $parts[0],
            'userId' => $parts[1],
        ];
    }
}
