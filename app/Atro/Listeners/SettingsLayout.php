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
use Atro\Core\Utils\HTMLSanitizer;

class SettingsLayout extends AbstractLayoutListener
{
    public function settings(Event $event): void
    {
        $layout = $event->getArgument('result');

        if (HTMLSanitizer::isParserConfigurable() || !is_array($layout)) {
            return;
        }

        $event->setArgument('result', $this->hideField($layout, HTMLSanitizer::LEGACY_PARSER_CONFIG_KEY));
    }

    protected function hideField(array $layout, string $name): array
    {
        foreach ($layout as $panelKey => $panel) {
            foreach ($panel['rows'] ?? [] as $rowKey => $row) {
                if (!is_array($row)) {
                    continue;
                }

                foreach ($row as $cellKey => $cell) {
                    if (is_array($cell) && ($cell['name'] ?? null) === $name) {
                        $layout[$panelKey]['rows'][$rowKey][$cellKey] = false;
                    }
                }
            }
        }

        return $layout;
    }
}
