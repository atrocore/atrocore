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
use Atro\Core\Utils\Util;

class TranslationLayout extends AbstractLayoutListener
{
    protected function getAllUiLanguages(): array
    {
        return array_unique(array_column($this->getConfig()->get('locales', []), 'language'));
    }

    protected function list(Event $event)
    {
        $result = $event->getArgument('result');

        foreach ($this->getAllUiLanguages() as $language) {
            $result[] = ['name' => Util::toCamelCase(strtolower($language))];
        }

        $event->setArgument('result', $result);
    }

    protected function detail(Event $event)
    {
        $result = $event->getArgument('result');

        foreach ($this->getAllUiLanguages() as $language) {
            $result[0]['rows'][] = [['name' => Util::toCamelCase(strtolower($language)), 'fullWidth' => true]];
        }

        $event->setArgument('result',  $result);
    }
}
