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

class AttributeLayout extends AbstractLayoutListener
{
    public function detail(Event $event): void
    {
        /** @var array $result */
        $result = $event->getArgument('result');

        foreach ($result as $panel) {
            foreach ($panel['rows'] as $row) {
                if (in_array('isMultilang', array_column($row, 'name'))) {
                    return;
                }
            }
        }

        if ($this->getConfig()->get('isMultilangActive', false)) {
            $multilangField = ['name' => 'isMultilang', 'inlineEditDisabled' => false];

            $result[0]['rows'][] = [$multilangField, false];
        }

        $event->setArgument('result', $result);
    }
}
