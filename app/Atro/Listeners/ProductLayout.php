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

class ProductLayout extends AbstractLayoutListener
{
    public function detail(Event $event)
    {
        if ($this->isCustomLayout($event)) {
            return;
        }

        $result = $event->getArgument('result');

        $result[] = [
            'label' => 'manufacturer',
            'rows'  => [
                [['name' => 'mpn'], false],
                [['name' => 'customsNumber'], ['name' => 'countryOfOrigin']]
            ]
        ];

        $result[] = [
            'label' => 'other',
            'rows'  => [
                [['name' => 'defaultSupplier'], ['name' => 'note']]
            ]
        ];

        $event->setArgument('result',  $result);
    }
}