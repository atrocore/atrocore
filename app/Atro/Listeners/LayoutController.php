<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Listeners;

use Atro\Core\EventManager\Event;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Util;

class LayoutController extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function afterActionRead(Event $event)
    {
        /** @var string $scope */
        $scope = $event->getArgument('params')['scope'];

        /** @var string $name */
        $name = $event->getArgument('params')['name'];

        /** @var bool $isAdminPage */
        $isAdminPage = $event->getArgument('request')->get('isAdminPage') === 'true';

        $method = 'modify' . $scope . ucfirst($name);
        $methodAdmin = $method . 'Admin';

        if (!$isAdminPage && method_exists($this, $method)) {
            $this->{$method}($event);
        } else {
            if ($isAdminPage && method_exists($this, $methodAdmin)) {
                $this->{$methodAdmin}($event);
            }
        }
    }

    protected function modifyTranslationList(Event $event)
    {
        $result = Json::decode($event->getArgument('result'), true);

        $languages = array_unique(array_column($this->getConfig()->get('locales', []), 'language'));
        foreach ($languages as $language) {
            $result[] = ['name' => Util::toCamelCase(strtolower($language))];
        }

        $event->setArgument('result', Json::encode($result));
    }

    protected function modifyTranslationDetail(Event $event)
    {
        $result = Json::decode($event->getArgument('result'), true);

        $languages = array_unique(array_column($this->getConfig()->get('locales', []), 'language'));
        foreach ($languages as $language) {
            $result[0]['rows'][] = [['name' => Util::toCamelCase(strtolower($language)), 'fullWidth' => true]];
        }

        $event->setArgument('result', Json::encode($result));
    }

    protected function modifyTranslationDetailSmall(Event $event)
    {
        $this->modifyTranslationDetail($event);
    }

    protected function modifyActionDetailSmall(Event $event): void
    {
        $result = Json::decode($event->getArgument('result'), true);

        $result[0]['rows'][] = [['name' => 'ActionSetLinker__sortOrder'], ['name' => 'ActionSetLinker__isActive']];

        $event->setArgument('result', Json::encode($result));
    }

    protected function modifyActionListSmall(Event $event): void
    {
        $result = Json::decode($event->getArgument('result'), true);

        $result[] = ['name' => 'ActionSetLinker__isActive'];

        $event->setArgument('result', Json::encode($result));
    }

    protected function modifyActionRelationships(Event $event): void
    {
        $result = Json::decode($event->getArgument('result'), true);

        $result[] = ['name' => 'actions'];

        $event->setArgument('result', Json::encode($result));
    }

    protected function modifyEmailTemplateDetail(Event $event): void
    {
        if ($this->getContainer()->get('layout')->isCustom('EmailTemplate', $event->getArgument('params')['name'])) {
            return;
        }

        $result = Json::decode($event->getArgument('result'), true);
        $newRows = [];
        foreach ($result[0]['rows'] as $row) {
            $newRows[] = $row;
            if (in_array($row[0]['name'], ['subject', 'body'])) {
                foreach ($this->getConfig()->get('locales') as $locale) {
                    if ($locale['language'] === 'en_US') {
                        continue;
                    }
                    $preparedLocale = ucfirst(Util::toCamelCase(strtolower($locale['language'])));
                    $newRows[] = [['name' => $row[0]['name'] . $preparedLocale, 'fullWidth' => true]];
                }
            }
        }
        $result[0]['rows'] = $newRows;

        $event->setArgument('result', Json::encode($result));
    }

    protected function modifyEmailTemplateDetailSmall(Event $event): void
    {
        $this->modifyEmailTemplateDetail($event);
    }

    protected function modifyNotificationTemplateDetail(Event $event): void
    {
        if ($this->getContainer()->get('layout')->isCustom('NotificationTemplate', $event->getArgument('params')['name'])) {
            return;
        }

        $result = Json::decode($event->getArgument('result'), true);
        $newRows = [];
        foreach ($result[0]['rows'] as $row) {
            $newRows[] = $row;
            if (in_array($row[0]['name'], ['subject', 'body'])) {
                foreach ($this->getConfig()->get('locales') as $locale) {
                    if ($locale['language'] === $this->getConfig()->get('mainLanguage')) {
                        continue;
                    }
                    $preparedLocale = ucfirst(Util::toCamelCase(strtolower($locale['language'])));
                    $newRows[] = [['name' => $row[0]['name'] . $preparedLocale, 'fullWidth' => true]];
                }
            }
        }
        $result[0]['rows'] = $newRows;

        $event->setArgument('result', Json::encode($result));
    }

    protected function modifyNotificationTemplateDetailSmall(Event $event): void
    {
        $this->modifyNotificationTemplateDetail($event);
    }

    protected function modifyNotificationRuleDetail(Event $event): void
    {

        $result = Json::decode($event->getArgument('result'), true);

        $rows = [];

        foreach(array_keys(($this->getMetadata()->get(['app','notificationTransports'], []))) as $transport){
            $rows[] = [["name" => $transport.'Active'], ["name" => $transport.'TemplateId']];
        }

        $result[] = [
            "label" => "Transport",
            "rows" => $rows
        ];

        $event->setArgument('result', Json::encode($result));
    }

    protected function modifyNotificationRuleDetailSmall(Event $event): void
    {
        $this->modifyNotificationRuleDetail($event);
    }
}
