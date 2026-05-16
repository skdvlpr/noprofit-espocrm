<?php

namespace Espo\Modules\GoogleIntegration\Tools\OAuth;

use Espo\Core\Utils\Config;

/**
 * Canonical redirect URI — must match authorize request, token exchange, and Google Console.
 *
 * Primary form matches Espo core {@see \Espo\Core\ExternalAccount\ClientManager::createOAuth2()}:
 * {@code siteUrl + '?entryPoint=oauthCallback'} (no extra slash before {@code ?}).
 */
final class RedirectUri
{
    public static function build(Config $config): string
    {
        return (string) ($config->get('siteUrl') ?? '') . '?entryPoint=oauthCallback';
    }

    /**
     * @return list<string>
     */
    public static function allowedList(Config $config): array
    {
        $canonical = self::build($config);
        $siteUrl = rtrim((string) ($config->get('siteUrl') ?? ''), '/');
        $withSlash = $siteUrl . '/?entryPoint=oauthCallback';

        $list = [$canonical];

        if ($withSlash !== $canonical) {
            $list[] = $withSlash;
        }

        return array_values(array_unique($list));
    }

    public static function resolve(Config $config, ?string $fromClient): string
    {
        $allowed = self::allowedList($config);

        if (is_string($fromClient) && $fromClient !== '') {
            foreach ($allowed as $uri) {
                if ($fromClient === $uri) {
                    return $fromClient;
                }
            }
        }

        return self::build($config);
    }
}
