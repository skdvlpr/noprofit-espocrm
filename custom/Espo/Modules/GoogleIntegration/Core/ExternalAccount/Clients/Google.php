<?php

namespace Espo\Modules\GoogleIntegration\Core\ExternalAccount\Clients;

use Espo\Core\Exceptions\Error;
use Espo\Core\ExternalAccount\Clients\Google as BaseGoogle;
use Espo\Core\Utils\Json;

/**
 * Google OAuth client with actionable errors when token exchange fails.
 */
class Google extends BaseGoogle
{
    private string $lastAuthCodeForLog = '';

    protected function getPingUrl()
    {
        return 'https://www.googleapis.com/calendar/v3/users/me/calendarList';
    }

    /**
     * @return ?array{
     *   accessToken: ?string,
     *   tokenType: ?string,
     *   expiresAt: ?string,
     *   refreshToken: ?string,
     * }
     */
    public function getAccessTokenFromAuthorizationCode(string $code)
    {
        $redirectUri = $this->getParam('redirectUri');
        $this->lastAuthCodeForLog = $code;

        $response = $this->client->getAccessToken(
            $this->getParam('tokenEndpoint'),
            \Espo\Core\ExternalAccount\OAuth2\Client::GRANT_TYPE_AUTHORIZATION_CODE,
            [
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]
        );

        if ($response['code'] !== 200) {
            $this->logTokenExchangeFailure($response);

            return null;
        }

        if (empty($response['result']) || !is_array($response['result'])) {
            $this->logTokenExchangeFailure($response);

            return null;
        }

        /** @var array<string, mixed> $result */
        $result = $response['result'];

        $data = $this->getAccessTokenDataFromResponseResult($result);
        $data['refreshToken'] = $result['refresh_token'] ?? null;

        if (empty($data['accessToken'])) {
            $this->logTokenExchangeFailure($response);

            return null;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function logTokenExchangeFailure(array $response): void
    {
        $result = $response['result'] ?? null;
        $detail = '';

        if (is_array($result)) {
            $error = $result['error'] ?? '';
            $description = $result['error_description'] ?? '';

            if (is_string($error) && $error !== '') {
                $detail = $error;

                if (is_string($description) && $description !== '') {
                    $detail .= ': ' . $description;
                }
            }
        }

        $redirectUri = (string) ($this->getParam('redirectUri') ?? '');
        $code = $this->getLastAuthCodeForLog();
        $codeFingerprint = $code !== '' ? substr(hash('sha256', $code), 0, 12) : '';

        $this->log->error(
            'Google OAuth token exchange failed. HTTP ' . ($response['code'] ?? '?')
            . ($detail !== '' ? ' — ' . $detail : '')
            . ' — redirect_uri=' . $redirectUri
            . ' — code_length=' . strlen($code)
            . ' — code_has_whitespace=' . (preg_match('/\s/', $code) ? '1' : '0')
            . ' — code_has_plus=' . (str_contains($code, '+') ? '1' : '0')
            . ' — code_has_slash=' . (str_contains($code, '/') ? '1' : '0')
            . ' — code_has_equal=' . (str_contains($code, '=') ? '1' : '0')
            . ' — code_fp=' . $codeFingerprint
            . ' — ' . Json::encode($response)
        );

        if ($detail !== '') {
            throw new Error('Google OAuth: ' . $detail);
        }
    }

    private function getLastAuthCodeForLog(): string
    {
        return $this->lastAuthCodeForLog;
    }
}
