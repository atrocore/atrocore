<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Migrations;

use Atro\Console\Cron;
use Atro\Core\Exceptions\Error;
use Atro\Core\Migration\Base;

class V1Dot12Dot0 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-12-04 18:00:00');
    }

    public function up(): void
    {
        file_put_contents(Cron::DAEMON_KILLER, '1');

        $this->getConfig()->set('workersCount', $this->getConfig()->get('queueManagerWorkersCount', 4));
        $this->getConfig()->save();

        $this->updateComposer('atrocore/core', '^1.12.0');
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited.');
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
