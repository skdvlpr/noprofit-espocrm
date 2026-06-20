<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

use Espo\Core\Htmlizer\TemplateRendererFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Log;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class CalendarTemplateApplier
{
    public function __construct(
        private EntityManager $entityManager,
        private TemplateRendererFactory $templateRendererFactory,
        private Config $config,
        private User $user,
        private Log $log
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function apply(?string $templateId, Entity $record, string $sourceDateType): array
    {
        if ($templateId === null || $templateId === '') {
            return [];
        }

        $template = $this->entityManager->getEntityById('CalendarTemplate', $templateId);

        if ($template === null || $template->get('targetEntityType') !== $record->getEntityType()) {
            return [];
        }

        return [
            'summary' => $this->render((string) ($template->get('summaryTemplate') ?? ''), $record, $sourceDateType),
            'description' => $this->render((string) ($template->get('descriptionTemplate') ?? ''), $record, $sourceDateType),
            'location' => $this->render((string) ($template->get('locationTemplate') ?? ''), $record, $sourceDateType),
            'colorId' => $template->get('colorId') ?? '',
            'visibility' => $template->get('visibility') ?: 'default',
            'transparency' => $template->get('transparency') ?: 'opaque',
            'reminderMode' => $template->get('reminderMode') ?: 'none',
            'reminders' => $template->get('reminders') ?: [],
            'calendarId' => $template->get('defaultGoogleCalendarId') ?: 'primary',
            'fieldValueTemplateMap' => $template->get('fieldValueTemplateMap') ?: [],
        ];
    }

    public function render(string $template, Entity $record, string $sourceDateType): string
    {
        if (trim($template) === '') {
            return '';
        }

        $template = $this->resolveRelatedTemplateVariables($record, $template);

        $rendered = trim($this->templateRendererFactory
            ->create()
            ->setEntity($record)
            ->setUser($this->user)
            ->setData([
                'espocrmUrl' => $this->buildRecordUrl($record),
                'sourceDateType' => $sourceDateType,
            ])
            ->setTemplate($template)
            ->render());

        return GoogleCalendarPlainText::normalize($rendered);
    }

    private function resolveRelatedTemplateVariables(Entity $record, string $template): string
    {
        $template = preg_replace_callback(
            '/{{\s*([A-Za-z][A-Za-z0-9_]*)\.([A-Za-z][A-Za-z0-9_]*)\s*}}/',
            function (array $matches) use ($record): string {
                return $this->escapeTemplateValue(
                    $this->getRelatedScalarValue($record, $matches[1], $matches[2])
                );
            },
            $template
        );

        return preg_replace_callback(
            '/{{\s*([A-Za-z][A-Za-z0-9_]*)\s*}}/',
            function (array $matches) use ($record): string {
                if (!in_array($matches[1], $record->getRelationList(), true)) {
                    return $matches[0];
                }

                return $this->escapeTemplateValue(
                    $this->getRelatedScalarValue($record, $matches[1], 'name')
                );
            },
            $template
        );
    }

    private function getRelatedScalarValue(Entity $record, string $relation, string $field): string
    {
        try {
            $relationType = $record->getRelationType($relation);
            $repository = $this->entityManager->getRelation($record, $relation);

            if (in_array($relationType, [Entity::BELONGS_TO, Entity::BELONGS_TO_PARENT, Entity::HAS_ONE], true)) {
                $related = $repository->findOne();

                return $related ? $this->stringifyScalar($related->get($field)) : '';
            }

            $values = [];

            foreach ($repository->limit(0, 20)->find() as $related) {
                $value = $this->stringifyScalar($related->get($field));

                if ($value !== '') {
                    $values[] = $value;
                }
            }

            return implode(', ', $values);
        } catch (\Throwable $e) {
            $this->log->warning(
                'Google Calendar template variable skipped: '
                . $record->getEntityType() . '.' . $relation . '.' . $field . ' - ' . $e->getMessage()
            );

            return '';
        }
    }

    private function stringifyScalar(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }

    private function escapeTemplateValue(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function buildRecordUrl(Entity $record): string
    {
        $siteUrl = rtrim((string) ($this->config->get('siteUrl') ?? ''), '/');

        return $siteUrl !== '' ? $siteUrl . '/#' . $record->getEntityType() . '/view/' . $record->getId() : '';
    }
}
