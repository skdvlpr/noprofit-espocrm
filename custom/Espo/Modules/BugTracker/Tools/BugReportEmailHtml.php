<?php

declare(strict_types=1);

namespace Espo\Modules\BugTracker\Tools;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * HTML-escapes BugReport fields before EmailTemplate substitution.
 *
 * Espo's EmailTemplate Formatter runs nl2br() on text fields in HTML mode
 * without htmlspecialchars. The installer seeds an HTML template that
 * interpolates {BugReport.description} / {BugReport.pageUrl} into markup,
 * so untrusted reporter input would otherwise become live HTML in technician
 * mail (trusted CRM SMTP).
 */
final class BugReportEmailHtml
{
    /** @var list<string> */
    public const ATTRIBUTES = ['name', 'description', 'pageUrl', 'pageTitle'];

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * In-memory copy for template processing. Never persist the result.
     */
    public static function copyForTemplate(EntityManager $entityManager, Entity $bugReport): Entity
    {
        $copy = $entityManager->getNewEntity($bugReport->getEntityType());
        $values = get_object_vars($bugReport->getValueMap());
        unset($values['id']);
        $copy->set($values);

        foreach (self::ATTRIBUTES as $attribute) {
            $raw = $copy->get($attribute);

            if (!is_string($raw) || $raw === '') {
                continue;
            }

            $copy->set($attribute, self::escape($raw));
        }

        return $copy;
    }
}
