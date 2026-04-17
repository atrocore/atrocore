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

class UserProfileLayout extends AbstractLayoutListener
{
    public function detail(Event $event): void
    {
        $result = $event->getArgument('result');

        if (!$this->getConfig()->get('disableNavigationPath', false) && !str_contains(json_encode($result), '"disableNavigationPath"')) {
            $key = array_search('Preferences', array_column($result, 'label'));

            if ($key !== false) {
                $result[$key]['rows'][] = [['name' => 'disableNavigationPath'], false];

                $event->setArgument('result', $result);
            }
        }
    }
}
