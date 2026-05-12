<?php

namespace Espo\Modules\SafehouseCrm\Jobs;

use Espo\Core\Job\JobDataLess;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\DateTime;

/**
 * Daily sync of an entity's status against an activity window [startField, endField].
 *
 * Behavior:
 *   - Records currently "active" whose window no longer covers today are flipped to inactive.
 *   - Records currently "inactive" whose window covers today again are flipped to active.
 *
 * Each subclass declares the entity type, the two date fields, the status field
 * and the two status values. The actual save() goes through the entity manager,
 * which triggers the entity's before-save formula and so keeps the on-save logic
 * and the nightly job in agreement.
 */
abstract class AbstractStatusWindowSync implements JobDataLess
{
    protected const BATCH_SIZE = 100;

    public function __construct(protected EntityManager $entityManager)
    {
    }

    abstract protected function entityType(): string;

    abstract protected function startField(): string;

    abstract protected function endField(): string;

    abstract protected function statusField(): string;

    abstract protected function activeValue(): string;

    abstract protected function inactiveValue(): string;

    public function run(): void
    {
        $today = date(DateTime::SYSTEM_DATE_FORMAT);

        foreach ($this->findStaleActive($today) as $record) {
            $record->set($this->statusField(), $this->inactiveValue());

            $this->entityManager->saveEntity($record);
        }

        foreach ($this->findStaleInactive($today) as $record) {
            $record->set($this->statusField(), $this->activeValue());

            $this->entityManager->saveEntity($record);
        }
    }

    /**
     * Records that are currently active but should be inactive (window already closed
     * or not yet open).
     */
    private function findStaleActive(string $today): iterable
    {
        return $this->entityManager
            ->getRDBRepository($this->entityType())
            ->where([
                $this->statusField() => $this->activeValue(),
                'OR' => [
                    [$this->startField() . '>' => $today],
                    [$this->endField() . '<' => $today],
                ],
            ])
            ->limit(0, static::BATCH_SIZE)
            ->find();
    }

    /**
     * Records that are currently inactive but should be active (today is inside
     * the open-ended window).
     */
    private function findStaleInactive(string $today): iterable
    {
        return $this->entityManager
            ->getRDBRepository($this->entityType())
            ->where([$this->statusField() => $this->inactiveValue()])
            ->where([
                'OR' => [
                    [$this->startField() => null],
                    [$this->startField() . '<=' => $today],
                ],
            ])
            ->where([
                'OR' => [
                    [$this->endField() => null],
                    [$this->endField() . '>=' => $today],
                ],
            ])
            ->limit(0, static::BATCH_SIZE)
            ->find();
    }
}
