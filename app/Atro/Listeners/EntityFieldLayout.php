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

class EntityFieldLayout extends AbstractLayoutListener
{
    public function detail(Event $event): void
    {
        if (empty($locales = $this->getConfig()->get('inputLanguageList', []))) {
            return;
        }

        $result = $event->getArgument('result');

        $newRows = [];
        foreach ($locales as $locale) {
            $preparedLocale = ucfirst(Util::toCamelCase(strtolower($locale)));

            $newRows[] = [
                ['name' => 'script' . $preparedLocale],
                ['name' => 'preview' . $preparedLocale],
            ];
        }

        if (!empty($newRows)) {
            foreach ($result as $panelIndex => $panel) {
                $rows = $panel['rows'] ?? [];

                foreach ($rows as $rowIndex => $row) {
                    if (!in_array('preview', array_column($row, 'name'))) {
                        continue;
                    }

                    if ($rowIndex === count($rows) - 1) {
                        $result[$panelIndex]['rows'] = array_merge($rows, $newRows);
                    } else {
                        array_splice($result[$panelIndex]['rows'], $rowIndex + 1, 0, $newRows);
                    }

                    $event->setArgument('result', $result);

                    return;
                }
            }
        }
    }
}
