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
use Atro\Core\LayoutManager;

class SoftwarePackageLayout extends AbstractLayoutListener
{
    public function listModulesToSelect(Event $event): void
    {
        if ($this->isCustomLayout($event) || !empty($event->getArgument('result'))) {
            return;
        }

        $params = $event->getArgument('params');

        $data = $this->getLayoutManager()->get(
            $params['scope'], 'list', null, null,
            $params['layoutProfileId'] ?? null, $params['isAdminPage']
        );

        $result = array_values(array_filter(
            $data['layout'] ?? [],
            fn(array $item) => ($item['name'] ?? null) !== 'installed'
        ));

        $event->setArgument('result', $result);
    }

    protected function getLayoutManager(): LayoutManager
    {
        return $this->getContainer()->get('layoutManager');
    }
}
