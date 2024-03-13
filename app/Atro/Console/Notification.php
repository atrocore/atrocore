<?php
/**
* AtroCore Software
*
* This source file is available under GNU General Public License version 3 (GPLv3).
* Full copyright and license information is available in LICENSE.txt, located in the root directory.
*
*  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
*  @license    GPLv3 (https://www.gnu.org/licenses/)
*/

declare(strict_types=1);

namespace Atro\Console;

use Atro\Core\Application;
use Espo\Repositories\Notification as NotificationRepository;

/**
 * Class Notification
 */
class Notification extends AbstractConsole
{
    /**
     * @inheritdoc
     */
    public static function getDescription(): string
    {
        return 'Refresh users notifications cache.';
    }

    /**
     * @inheritdoc
     */
    public function run(array $data): void
    {
        if (empty($this->getConfig()->get('isInstalled')) || Application::isSystemUpdating()) {
            exit(1);
        }

        NotificationRepository::refreshNotReadCount($this->getContainer()->get('connection'));

        self::show('Users notifications cache refreshed successfully', self::SUCCESS, true);
    }
}
