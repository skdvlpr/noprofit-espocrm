<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\PrimaNota;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use stdClass;

/**
 * Find a PrimaNota by donationPaymentReference including soft-deleted rows,
 * and restore when deleted. Used by donation-site ingest (API user is not admin,
 * so core restoreDeleted action is unavailable).
 */
class RestoreByDonationReference
{
    private const string ENTITY_TYPE = 'PrimaNota';

    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
    ) {}

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    public function restore(string $donationPaymentReference): stdClass
    {
        if (!$this->acl->check(self::ENTITY_TYPE, Table::ACTION_CREATE) ||
            !$this->acl->check(self::ENTITY_TYPE, Table::ACTION_EDIT)
        ) {
            throw new Forbidden('No access to restore PrimaNota.');
        }

        $reference = trim($donationPaymentReference);
        if ($reference === '') {
            throw new BadRequest('donationPaymentReference is required.');
        }

        if (!str_starts_with($reference, '#')) {
            $reference = '#' . $reference;
        }

        $live = $this->findByReference($reference, withDeleted: false);
        if ($live !== null) {
            return (object) [
                'id' => $live->getId(),
                'donationPaymentReference' => $reference,
                'restored' => false,
                'alreadyLive' => true,
            ];
        }

        $deleted = $this->findByReference($reference, withDeleted: true);
        if ($deleted === null) {
            throw new NotFound("PrimaNota not found for {$reference}.");
        }

        if (!$deleted->get(Attribute::DELETED)) {
            return (object) [
                'id' => $deleted->getId(),
                'donationPaymentReference' => $reference,
                'restored' => false,
                'alreadyLive' => true,
            ];
        }

        $this->entityManager
            ->getRDBRepository(self::ENTITY_TYPE)
            ->restoreDeleted($deleted->getId());

        return (object) [
            'id' => $deleted->getId(),
            'donationPaymentReference' => $reference,
            'restored' => true,
            'alreadyLive' => false,
        ];
    }

    private function findByReference(string $reference, bool $withDeleted): ?Entity
    {
        $builder = $this->entityManager
            ->getQueryBuilder()
            ->select()
            ->from(self::ENTITY_TYPE)
            ->where([
                'donationPaymentReference' => $reference,
            ])
            ->order('modifiedAt', 'DESC')
            ->limit(0, 1);

        if ($withDeleted) {
            $builder->withDeleted();
        }

        return $this->entityManager
            ->getRDBRepository(self::ENTITY_TYPE)
            ->clone($builder->build())
            ->findOne();
    }
}
