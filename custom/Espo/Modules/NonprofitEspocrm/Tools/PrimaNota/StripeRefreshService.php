<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\PrimaNota;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Acl;
use Espo\Core\HttpClient;
use Espo\Core\HttpClient\ClientFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Log;
use Espo\ORM\EntityManager;
use stdClass;

/**
 * Asks the donation site to re-read Stripe and update PrimaNota.paymentStatus.
 * Stripe secrets stay on the site; CRM only holds site URL + shared sync token.
 */
class StripeRefreshService
{
    private const int CONNECT_TIMEOUT = 5;
    private const int TIMEOUT = 45;

    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private Config $config,
        private ClientFactory $clientFactory,
        private Log $log,
    ) {}

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     * @throws Error
     */
    public function refresh(string $id): stdClass
    {
        $entity = $this->entityManager->getEntityById('PrimaNota', $id);

        if (!$entity) {
            throw new NotFound("PrimaNota {$id} not found.");
        }

        if (!$this->acl->checkEntityRead($entity) || !$this->acl->checkEntityEdit($entity)) {
            throw new Forbidden("No access to PrimaNota.");
        }

        if ((string) $entity->get('donationPaymentProvider') !== 'Stripe') {
            throw new BadRequest("PrimaNota is not Stripe-sourced.");
        }

        $siteUrl = rtrim(trim((string) $this->config->get('safehouseDonationSiteUrl')), '/');
        $token = trim((string) $this->config->get('safehouseCrmSyncToken'));

        if ($siteUrl === '' || $token === '') {
            // 400 so the UI shows a clear toast (not opaque Internal server error).
            throw new BadRequest(
                'Donation site sync is not configured. Set safehouseDonationSiteUrl and safehouseCrmSyncToken in CRM config.'
            );
        }

        $url = $siteUrl . '/api/internal/prima-nota/refresh-from-stripe';
        $payload = Json::encode(['primaNotaId' => $id]);

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
            $this->log->error('PrimaNota Stripe refresh connect error: ' . $e->getMessage());

            throw new Error('Could not reach donation site for Stripe refresh.', previous: $e);
        } catch (HttpClient\Exceptions\TooManyRedirectsException $e) {
            throw new Error('Donation site Stripe refresh redirected unexpectedly.', previous: $e);
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

            $this->log->warning('PrimaNota Stripe refresh failed: ' . $message, [
                'primaNotaId' => $id,
                'httpStatus' => $status,
            ]);

            throw new Error($message);
        }

        $this->entityManager->refreshEntity($entity);

        $site = $decoded ?? (object) [];
        $reason = is_object($site) && isset($site->reason) ? (string) $site->reason : '';
        $applyError = is_object($site) && isset($site->applyError) ? (string) $site->applyError : '';

        return (object) [
            'id' => $id,
            'paymentStatus' => $entity->get('paymentStatus'),
            'stripePayoutId' => $entity->get('stripePayoutId'),
            'stripePayoutPaidAt' => $entity->get('stripePayoutPaidAt'),
            'reason' => $reason !== '' ? $reason : null,
            'applyError' => $applyError !== '' ? $applyError : null,
            'site' => $site,
        ];
    }
}
