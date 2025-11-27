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

use Atro\Core\Migration\Base;

class V2Dot1Dot30 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-11-25 18:00:00');
    }

    public function up(): void
    {
        $this->getConnection()->createQueryBuilder()
            ->update('layout')
            ->set('view_type', ':newType')
            ->where('view_type = :oldType')
            ->setParameter('newType', 'navigation')
            ->setParameter('oldType', 'leftSidebar')
            ->executeStatement();

        $this->getConnection()->createQueryBuilder()
            ->update('layout')
            ->set('view_type', ':newType')
            ->where('view_type = :oldType')
            ->setParameter('newType', 'summary')
            ->setParameter('oldType', 'rightSideView')
            ->executeStatement();
    }
}
