<?php

namespace Espo\Modules\GoogleIntegration\Controllers;

use Espo\Controllers\ExternalAccount as BaseExternalAccount;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Core\HookManager;
use Espo\Modules\GoogleIntegration\Tools\ExternalAccount\IdParser;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use Espo\Modules\GoogleIntegration\Tools\OAuth\AuthorizationCodeHandler;
use Espo\Modules\GoogleIntegration\Tools\OAuth\RedirectUri;
use stdClass;

/**
 * External account API: host guard, safe id parsing, canonical Google redirect URI.
 */
class ExternalAccount extends BaseExternalAccount
{
    public function putActionUpdate(Request $request, Response $response): stdClass
    {
        return parent::putActionUpdate($request, $response);
    }

    public function getActionGetOAuth2Info(Request $request): ?stdClass
    {
        $this->assertRequestHostMatchesSiteUrl($request);

        $id = $request->getQueryParam('id');

        if (!is_string($id) || $id === '') {
            throw new BadRequest();
        }

        $parsed = IdParser::parse($id);

        if ($this->user->getId() !== $parsed['userId'] && !$this->user->isAdmin()) {
            throw new Forbidden();
        }

        $result = parent::getActionGetOAuth2Info($request);

        if ($result === null) {
            return null;
        }

        if ($parsed['integration'] !== Installer::INTEGRATION_ID) {
            return $result;
        }

        $result->redirectUri = RedirectUri::build($this->config);

        return $result;
    }

    public function postActionAuthorizationCode(Request $request): bool
    {
        $this->assertRequestHostMatchesSiteUrl($request);

        $data = $request->getParsedBody();

        $id = $data->id ?? null;

        if (!is_string($id) || $id === '') {
            throw new BadRequest('Missing external account id.');
        }

        $parsed = IdParser::parse($id);

        if ($this->user->getId() !== $parsed['userId'] && !$this->user->isAdmin()) {
            throw new Forbidden();
        }

        $code = $data->code ?? null;

        if (!is_string($code)) {
            throw new BadRequest('Missing or invalid OAuth authorization code.');
        }

        $code = trim($code);

        if ($code === '' || strlen($code) < 10) {
            throw new BadRequest('Missing or invalid OAuth authorization code.');
        }

        $redirectUri = $data->redirectUri ?? null;

        if ($parsed['integration'] !== Installer::INTEGRATION_ID) {
            return parent::postActionAuthorizationCode($request);
        }

        try {
            $handler = new AuthorizationCodeHandler(
                $this->entityManager,
                $this->getContainer()->getByClass(ClientManager::class),
                $this->config,
                $this->getContainer()->getByClass(HookManager::class),
            );

            return $handler->exchange(
                $parsed['userId'],
                $code,
                is_string($redirectUri) ? $redirectUri : null
            );
        } catch (Error $e) {
            if (str_starts_with($e->getMessage(), 'Google OAuth:')) {
                throw $e;
            }

            throw new Error(
                'Could not get access token for ' . Installer::INTEGRATION_ID . '.'
                . ' Copy Redirect URI from Administration → Integrations → Google calendar & drive: '
                . RedirectUri::build($this->config)
            );
        }
    }

    private function assertRequestHostMatchesSiteUrl(Request $request): void
    {
        $siteUrl = $this->config->get('siteUrl');

        if (!is_string($siteUrl) || $siteUrl === '') {
            return;
        }

        $siteHost = parse_url($siteUrl, PHP_URL_HOST);
        $sitePort = parse_url($siteUrl, PHP_URL_PORT);
        $siteScheme = parse_url($siteUrl, PHP_URL_SCHEME);
        $requestHost = $request->getServerParam('HTTP_HOST');

        if (
            !is_string($siteHost)
            || $siteHost === ''
            || !is_string($requestHost)
            || $requestHost === ''
        ) {
            return;
        }

        $normalizedSiteHost = $this->normalizeHostWithPort(
            $siteHost,
            is_int($sitePort) ? $sitePort : null,
            is_string($siteScheme) ? $siteScheme : null
        );
        $normalizedRequestHost = $this->normalizeRequestHost($requestHost, is_string($siteScheme) ? $siteScheme : null);

        if ($normalizedRequestHost === null) {
            return;
        }

        if (strcasecmp($normalizedSiteHost, $normalizedRequestHost) !== 0) {
            throw new BadRequest(
                'Current URL host (' . $requestHost . ') does not match Administration → Settings → Site URL ('
                . $normalizedSiteHost . '). Open Espo at ' . rtrim($siteUrl, '/') . ' and retry Google connect.'
            );
        }
    }

    private function normalizeRequestHost(string $requestHost, ?string $siteScheme): ?string
    {
        $parts = parse_url('http://' . $requestHost);

        if (!is_array($parts) || !isset($parts['host']) || !is_string($parts['host'])) {
            return null;
        }

        $port = $parts['port'] ?? null;

        return $this->normalizeHostWithPort(
            $parts['host'],
            is_int($port) ? $port : null,
            $siteScheme
        );
    }

    private function normalizeHostWithPort(string $host, ?int $port, ?string $scheme): string
    {
        $host = strtolower(trim($host, '[]'));

        if ($port === null || $this->isDefaultPort($port, $scheme)) {
            return $host;
        }

        return $host . ':' . $port;
    }

    private function isDefaultPort(int $port, ?string $scheme): bool
    {
        $scheme = strtolower((string) $scheme);

        return ($scheme === 'http' && $port === 80)
            || ($scheme === 'https' && $port === 443);
    }
}
