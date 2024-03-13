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

namespace Atro\Console;

use Atro\Core\Application;

/**
 * Class QueueManager
 */
class QueueManager extends AbstractConsole
{
    /**
     * @inheritdoc
     */
    public static function getDescription(): string
    {
        return 'Run Queue Manager job.';
    }

    /**
     * @inheritdoc
     */
    public function run(array $data): void
    {
        if (empty($this->getConfig()->get('isInstalled')) || Application::isSystemUpdating()) {
            exit(1);
        }

        $itemId = $data['id'] ?? \Atro\Core\QueueManager::getItemId();;

        if ($itemId !== null) {
            $this->getContainer()->get('queueManager')->run((int)$data['stream'], (string)$itemId);
        }

        self::show('Queue Manager run successfully', self::SUCCESS, true);
    }
}
