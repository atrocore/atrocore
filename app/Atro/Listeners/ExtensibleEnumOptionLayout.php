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

class ExtensibleEnumOptionLayout extends AbstractLayoutListener
{
    public function detail(Event $event): void
    {
        if ($this->getRelatedEntity($event) === 'ExtensibleEnum') {
            $result = $event->getArgument('result');
            $jsonString = json_encode($result);

            if (!str_contains($jsonString, '"ExtensibleEnumExtensibleEnumOption__sorting"')) {
                if (str_contains($jsonString, '"sort_order"')) {
                    $result = json_decode(
                        str_replace('"sort_order"', '"ExtensibleEnumExtensibleEnumOption__sorting"', $jsonString)
                        , true);
                } else {
                    $result[0]['rows'][] = [['name' => 'ExtensibleEnumExtensibleEnumOption__sorting'], false];
                }
            }

            $event->setArgument('result', $result);
        }
    }
}
