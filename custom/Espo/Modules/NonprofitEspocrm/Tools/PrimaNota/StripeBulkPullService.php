<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\PrimaNota;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\HttpClient;
use Espo\Core\HttpClient\ClientFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Log;
use Espo\Entities\User;
use RuntimeException;
use stdClass;
use Throwable;

/**
 * Asks the donation site to list provider payments and upsert PrimaNota rows.
 * Stripe secrets stay on the site; CRM only holds site URL + shared sync token.
 */
class StripeBulkPullService
{
    private const int CONNECT_TIMEOUT = 10;
    /** Must exceed site bulk pull (ingest + payout status sync). */
    private const int TIMEOUT = 600;

    public function __construct(
        private Acl $acl,
        private Config $config,
        private ClientFactory $clientFactory,
        private Log $log,
        private User $user,
    ) {}

    /**
     * @param list<string>|array<int, string> $providers
     * @param list<string>|array<int, string>|null $currencies
     *
     * @throws BadRequest
     * @throws Forbidden
     * @throws Error
     */
    public function pull(
        array $providers,
        string $mode,
        ?string $fromDate = null,
        int $maxItems = 200,
        ?array $currencies = null,
    ): stdClass {
        if (!$this->acl->check('PrimaNota', Table::ACTION_CREATE) ||
            !$this->acl->check('PrimaNota', Table::ACTION_EDIT)
        ) {
            throw new Forbidden('No access to pull PrimaNota payments.');
        }

        $providers = array_values(array_unique(array_filter(
            array_map(static fn ($p) => trim((string) $p), $providers),
            static fn (string $p) => $p !== ''
        )));

        if ($providers === []) {
            throw new BadRequest('Select at least one payment provider.');
        }

        $currencyList = [];
        if (is_array($currencies)) {
            foreach ($currencies as $currency) {
                $code = strtoupper(trim((string) $currency));
                if ($code !== '' && preg_match('/^[A-Z]{3}$/', $code) && !in_array($code, $currencyList, true)) {
                    $currencyList[] = $code;
                }
            }
        }
        if ($currencyList === []) {
            $currencyList = ['EUR'];
        }

        $mode = $mode === 'from_date' ? 'from_date' : 'all';
        $fromDate = is_string($fromDate) ? trim($fromDate) : null;

        if ($mode === 'from_date') {
            if ($fromDate === null || $fromDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
                throw new BadRequest('fromDate must be YYYY-MM-DD when mode is from_date.');
            }
        } else {
            $fromDate = null;
        }

        $maxItems = max(1, min(500, $maxItems));

        $siteUrl = rtrim(trim((string) $this->config->get('safehouseDonationSiteUrl')), '/');
        $token = trim((string) $this->config->get('safehouseCrmSyncToken'));

        if ($siteUrl === '' || $token === '') {
            throw new BadRequest(
                'Donation site sync is not configured. Set safehouseDonationSiteUrl and safehouseCrmSyncToken in CRM config.'
            );
        }

        $url = $siteUrl . '/api/internal/prima-nota/bulk-pull';
        $payload = Json::encode([
            'providers' => $providers,
            'currencies' => $currencyList,
            'mode' => $mode,
            'fromDate' => $fromDate,
            'maxItems' => $maxItems,
        ]);

        $options = new HttpClient\Options(
            protocols: [HttpClient\Protocol::https, HttpClient\Protocol::http],
            redirect: new HttpClient\Options\Redirect(
                allow: false,
                protocols: [HttpClient\Protocol::https],
            ),
            timeout: self::TIMEOUT,
            connectTimeout: self::CONNECT_TIMEOUT,
            internalHostRestriction: new HttpClient\Options\InternalHostRestriction(
                restrict: false,
            ),
        );

        $request = HttpClient\RequestCreator::create('POST', $url)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Safehouse-Sync-Token', $token)
            ->withBody(HttpClient\Util::streamFor($payload));

        $client = $this->clientFactory->create($options);

        try {
            $response = $client->send($request);
        } catch (HttpClient\Exceptions\ConnectException $e) {
            $this->log->error('PrimaNota bulk pull connect error: ' . $e->getMessage());

            throw new Error('Could not reach donation site for bulk pull.', previous: $e);
        } catch (HttpClient\Exceptions\TooManyRedirectsException $e) {
            throw new Error('Donation site bulk pull redirected unexpectedly.', previous: $e);
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            $prev = $e->getPrevious();
            $prevMsg = $prev instanceof Throwable ? $prev->getMessage() : '';
            $combined = strtolower($msg . ' ' . $prevMsg);

            if (
                str_contains($combined, 'timed out') ||
                str_contains($combined, 'timeout') ||
                str_contains($combined, 'operation timed out')
            ) {
                $this->log->error('PrimaNota bulk pull timed out waiting for donation site.', [
                    'userId' => $this->user->getId(),
                    'timeout' => self::TIMEOUT,
                ]);

                throw new Error(
                    'Donation site is still processing the import (timeout). Wait a moment and refresh the list — records may already be imported.',
                    previous: $e
                );
            }

            $this->log->error('PrimaNota bulk pull transport error: ' . $msg);

            throw new Error('Could not reach donation site for bulk pull.', previous: $e);
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $decoded = null;

        if ($body !== '') {
            try {
                $decoded = Json::decode($body);
            } catch (\Throwable) {
                $decoded = null;
            }
        }

        if ($status < 200 || $status >= 300) {
            $message = is_object($decoded) && isset($decoded->message)
                ? (string) $decoded->message
                : "Donation site returned HTTP {$status}.";

            $this->log->warning('PrimaNota bulk pull failed: ' . $message, [
                'userId' => $this->user->getId(),
                'httpStatus' => $status,
                'providers' => $providers,
            ]);

            throw new Error($message);
        }

        $site = $decoded ?? (object) [];

        return (object) [
            'providers' => $providers,
            'currencies' => $currencyList,
            'mode' => $mode,
            'fromDate' => $fromDate,
            'scanned' => is_object($site) && isset($site->scanned) ? (int) $site->scanned : 0,
            'created' => is_object($site) && isset($site->created) ? (int) $site->created : 0,
            'updated' => is_object($site) && isset($site->updated) ? (int) $site->updated : 0,
            'duplicate' => is_object($site) && isset($site->duplicate) ? (int) $site->duplicate : 0,
            'skipped' => is_object($site) && isset($site->skipped) ? (int) $site->skipped : 0,
            'failed' => is_object($site) && isset($site->failed) ? (int) $site->failed : 0,
            'markedInviato' => is_object($site) && isset($site->markedInviato)
                ? (int) $site->markedInviato
                : 0,
            'statusRefreshed' => is_object($site) && isset($site->statusRefreshed)
                ? (int) $site->statusRefreshed
                : 0,
            'truncated' => is_object($site) && !empty($site->truncated),
            'unsupportedProviders' => is_object($site) && isset($site->unsupportedProviders)
                ? $site->unsupportedProviders
                : [],
            'skippedCurrencies' => is_object($site) && isset($site->skippedCurrencies)
                ? $site->skippedCurrencies
                : [],
            'errors' => is_object($site) && isset($site->errors) ? $site->errors : [],
            'log' => is_object($site) && isset($site->log) ? $site->log : [],
            'site' => $site,
        ];
    }
}
