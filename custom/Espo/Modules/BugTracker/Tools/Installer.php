<?php

declare(strict_types=1);

namespace Espo\Modules\BugTracker\Tools;

use Espo\Core\Container;
use Espo\Core\DataManager;
use Espo\Core\InjectableFactory;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Entities\EmailTemplate;

/**
 * Post-install: tab, default config + email templates, rebuild.
 * Does not create or mutate Roles — ACL is Administration → Roles only.
 */
class Installer
{
    private const ENTITY = 'BugReport';

    private const TEMPLATE_NEW_NAME = 'BugTracker — New report';
    private const TEMPLATE_CLOSED_NAME = 'BugTracker — Closed confirmation';

    public function runPostInstall(Container $container): void
    {
        /** @var InjectableFactory $injectableFactory */
        $injectableFactory = $container->getByClass(InjectableFactory::class);
        /** @var Config $config */
        $config = $container->getByClass(Config::class);
        /** @var EntityManager $em */
        $em = $container->getByClass(EntityManager::class);

        $this->ensureTab($config, $injectableFactory);
        $this->ensureDefaultConfigAndTemplates($config, $injectableFactory, $em);

        $container->getByClass(DataManager::class)->rebuild();
    }

    private function ensureTab(Config $config, InjectableFactory $injectableFactory): void
    {
        $tabList = $config->get('tabList', []) ?? [];

        if (!is_array($tabList)) {
            $tabList = [];
        }

        if ($this->tabListContains(self::ENTITY, $tabList)) {
            return;
        }

        $tabList[] = self::ENTITY;

        $configWriter = $injectableFactory->create(ConfigWriter::class);
        $configWriter->set('tabList', $tabList);
        $configWriter->save();
    }

    /**
     * @param array<int, mixed> $tabList
     */
    private function tabListContains(string $entityType, array $tabList): bool
    {
        foreach ($tabList as $item) {
            if ($item === $entityType) {
                return true;
            }

            if (is_object($item) && isset($item->itemList) && is_array($item->itemList)) {
                if ($this->tabListContains($entityType, $item->itemList)) {
                    return true;
                }
            }

            if (is_array($item) && isset($item['itemList']) && is_array($item['itemList'])) {
                if ($this->tabListContains($entityType, $item['itemList'])) {
                    return true;
                }
            }
        }

        return false;
    }

    private function ensureDefaultConfigAndTemplates(
        Config $config,
        InjectableFactory $injectableFactory,
        EntityManager $em
    ): void {
        $configWriter = $injectableFactory->create(ConfigWriter::class);
        $changed = false;

        if ($config->get('bugTrackerEnabled') === null) {
            $configWriter->set('bugTrackerEnabled', true);
            $changed = true;
        }

        $newTemplateId = $this->ensureEmailTemplate(
            $em,
            self::TEMPLATE_NEW_NAME,
            'New bug report: {BugReport.name}',
            '<p>A new bug report was submitted.</p>'
            . '<p><strong>{BugReport.name}</strong></p>'
            . '<p>Page: <a href="{BugReport.pageUrl}">{BugReport.pageUrl}</a></p>'
            . '<p>{BugReport.description}</p>'
        );

        $closedTemplateId = $this->ensureEmailTemplate(
            $em,
            self::TEMPLATE_CLOSED_NAME,
            'Bug closed: {BugReport.name}',
            '<p>Your bug report <strong>{BugReport.name}</strong> has been closed.</p>'
            . '<p>Thank you for helping improve the CRM.</p>'
        );

        if (!$config->get('bugTrackerNotifyEmailTemplateId') && $newTemplateId) {
            $configWriter->set('bugTrackerNotifyEmailTemplateId', $newTemplateId);
            $changed = true;
        }

        if (!$config->get('bugTrackerClosedEmailTemplateId') && $closedTemplateId) {
            $configWriter->set('bugTrackerClosedEmailTemplateId', $closedTemplateId);
            $changed = true;
        }

        if ($changed) {
            $configWriter->save();
        }
    }

    private function ensureEmailTemplate(
        EntityManager $em,
        string $name,
        string $subject,
        string $body
    ): ?string {
        /** @var ?EmailTemplate $existing */
        $existing = $em->getRDBRepositoryByClass(EmailTemplate::class)
            ->where(['name' => $name])
            ->findOne();

        if ($existing) {
            return $existing->getId();
        }

        /** @var EmailTemplate $template */
        $template = $em->getRDBRepositoryByClass(EmailTemplate::class)->getNew();
        $template->set([
            'name' => $name,
            'subject' => $subject,
            'body' => $body,
            'isHtml' => true,
            'oneOff' => false,
        ]);
        $em->saveEntity($template, [
            'skipAll' => true,
            'silent' => true,
        ]);

        return $template->getId();
    }
}
