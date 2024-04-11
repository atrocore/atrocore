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

        foreach ($this->getConfig()->get('interfaceLocales', []) as $locale) {
            $result[] = ['name' => Util::toCamelCase(strtolower($locale))];
        }

        $event->setArgument('result', Json::encode($result));
    }

    protected function modifyTranslationDetail(Event $event)
    {
        $result = Json::decode($event->getArgument('result'), true);

        foreach ($this->getConfig()->get('interfaceLocales', []) as $locale) {
            $result[0]['rows'][] = [['name' => Util::toCamelCase(strtolower($locale)), 'fullWidth' => true]];
        }

        $event->setArgument('result', Json::encode($result));
    }

    protected function modifyTranslationDetailSmall(Event $event)
    {
        $this->modifyTranslationDetail($event);
    }
}