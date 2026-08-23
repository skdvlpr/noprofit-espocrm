<?php

namespace Espo\Modules\GoogleIntegration\Core\ExternalAccount\Clients;

use Espo\Core\Exceptions\Error;
use Espo\Core\ExternalAccount\Clients\Google as BaseGoogle;
use Espo\Core\ExternalAccount\OAuth2\Client;
use Espo\Core\Utils\Json;
use Espo\Modules\GoogleIntegration\Tools\OAuth\RefreshToken;
use Throwable;

/**
 * Google OAuth client with actionable errors when token exchange fails.
 */
class Google extends BaseGoogle
{
    private string $lastAuthCodeForLog = '';

    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';
    private const CALENDAR_LIST_URL = 'https://www.googleapis.com/calendar/v3/users/me/calendarList';
    private const CALENDARS_URL = 'https://www.googleapis.com/calendar/v3/calendars';
    private const CALENDAR_EVENT_URL = 'https://www.googleapis.com/calendar/v3/calendars/%s/events';

    protected function getPingUrl()
    {
        return 'https://www.googleapis.com/calendar/v3/users/me/calendarList';
    }

    /**
     * @return ?array{
     *   accessToken: ?string,
     *   tokenType: ?string,
     *   expiresAt: ?string,
     *   refreshToken?: string,
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

        $refreshToken = RefreshToken::fromAuthorizationResult($result);

        if ($refreshToken !== null) {
            $data['refreshToken'] = $refreshToken;
        }

        if (empty($data['accessToken'])) {
            $this->logTokenExchangeFailure($response);

            return null;
        }

        return $data;
    }

    /**
     * @return array{
     *     googleAccountId?: string,
     *     googleAccountEmail?: string,
     *     googleAccountName?: string,
     *     googleAccountPicture?: string
     * }|null
     */
    public function getGoogleAccountProfile(): ?array
    {
        try {
            $result = $this->request(self::USERINFO_URL);
        } catch (Throwable $e) {
            $this->log->warning('Google OAuth profile fetch failed: ' . $e->getMessage());

            return null;
        }

        if (!is_array($result)) {
            return null;
        }

        $profile = [];

        foreach ([
            'sub' => 'googleAccountId',
            'email' => 'googleAccountEmail',
            'name' => 'googleAccountName',
            'picture' => 'googleAccountPicture',
        ] as $source => $target) {
            $value = $result[$source] ?? null;

            if (is_string($value) && $value !== '') {
                $profile[$target] = $value;
            }
        }

        return $profile !== [] ? $profile : null;
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    public function createCalendarEvent(array $event, string $calendarId = 'primary'): array
    {
        $result = $this->request(
            $this->buildCalendarEventUrl($calendarId),
            Json::encode($event),
            Client::HTTP_METHOD_POST,
            Client::CONTENT_TYPE_APPLICATION_JSON
        );

        return is_array($result) ? $result : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listCalendars(int $maxResults = 250): array
    {
        $result = $this->request(self::CALENDAR_LIST_URL . '?' . http_build_query([
            'maxResults' => max(1, min(250, $maxResults)),
            'minAccessRole' => 'reader',
            'showDeleted' => 'false',
        ]));

        return is_array($result['items'] ?? null) ? $result['items'] : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function insertCalendar(string $summary, string $timeZone = 'Europe/Rome'): array
    {
        $result = $this->request(
            self::CALENDARS_URL,
            Json::encode([
                'summary' => $summary,
                'timeZone' => $timeZone,
            ]),
            Client::HTTP_METHOD_POST,
            Client::CONTENT_TYPE_APPLICATION_JSON
        );

        return is_array($result) ? $result : [];
    }

    public function findCalendarIdBySummary(string $summary): ?string
    {
        $needle = trim($summary);

        if ($needle === '') {
            return null;
        }

        foreach ($this->listCalendars() as $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemSummary = trim((string) ($item['summary'] ?? ''));

            if (strcasecmp($itemSummary, $needle) !== 0) {
                continue;
            }

            $id = trim((string) ($item['id'] ?? ''));

            if ($id !== '') {
                return $id;
            }
        }

        return null;
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, nextPageToken?: string}
     */
    public function listCalendarEvents(
        string $calendarId,
        string $timeMin,
        string $timeMax,
        int $maxResults = 250,
        ?string $pageToken = null
    ): array {
        $query = [
            'timeMin' => $timeMin,
            'timeMax' => $timeMax,
            'singleEvents' => 'true',
            'orderBy' => 'startTime',
            'maxResults' => max(1, min(250, $maxResults)),
        ];

        if ($pageToken !== null && $pageToken !== '') {
            $query['pageToken'] = $pageToken;
        }

        $result = $this->request($this->buildCalendarEventUrl($calendarId) . '?' . http_build_query($query));

        return [
            'items' => is_array($result['items'] ?? null) ? $result['items'] : [],
            'nextPageToken' => is_string($result['nextPageToken'] ?? null) ? $result['nextPageToken'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    public function updateCalendarEvent(string $eventId, array $event, string $calendarId = 'primary'): array
    {
        $result = $this->request(
            $this->buildCalendarEventUrl($calendarId) . '/' . rawurlencode($eventId),
            Json::encode($event),
            Client::HTTP_METHOD_PUT,
            Client::CONTENT_TYPE_APPLICATION_JSON
        );

        return is_array($result) ? $result : [];
    }

    public function deleteCalendarEvent(string $eventId, string $calendarId = 'primary'): void
    {
        $this->request(
            $this->buildCalendarEventUrl($calendarId) . '/' . rawurlencode($eventId),
            null,
            Client::HTTP_METHOD_DELETE
        );
    }

    private function buildCalendarEventUrl(string $calendarId): string
    {
        return sprintf(self::CALENDAR_EVENT_URL, rawurlencode($calendarId));
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
