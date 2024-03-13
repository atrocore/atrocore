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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot8Dot26 extends Base
{
    public function up(): void
    {
        $this->getConnection()
            ->createQueryBuilder()
            ->delete('job')
            ->where('scheduled_job_id = :id')
            ->setParameter('id', 'DeleteForever')
            ->executeStatement();

        $this->getConnection()
            ->createQueryBuilder()
            ->delete('scheduled_job')
            ->where('id = :id')
            ->setParameter('id', 'DeleteForever')
            ->executeStatement();
    }

    public function down(): void
    {
    }
}
