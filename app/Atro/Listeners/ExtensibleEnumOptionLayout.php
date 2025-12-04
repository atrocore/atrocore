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
                if (str_contains($jsonString, '"sortOrder"')) {
                    $result = json_decode(
                        str_replace('"sortOrder"', '"ExtensibleEnumExtensibleEnumOption__sorting"', $jsonString)
                        , true);
                } else {
                    $result[0]['rows'][] = [['name' => 'ExtensibleEnumExtensibleEnumOption__sorting'], false];
                }
            }

            if (!$this->isAdminPage($event) && !str_contains($jsonString, ':"name"')) {
                array_splice($result[0]['rows'][0], 1, 0, [['name' => 'name']]);
            }

            $event->setArgument('result', $result);
        }
    }

    public function list(Event $event): void
    {
        if ($this->getRelatedEntity($event) === 'ExtensibleEnum' && !$this->isAdminPage($event)) {
            $result = $event->getArgument('result');

            if (!in_array('name', array_column($result, 'name'))) {
                array_splice($result, 2, 0, [['name' => 'name', 'link' => true]]);
            }

            $event->setArgument('result', $result);
        }
    }
}
