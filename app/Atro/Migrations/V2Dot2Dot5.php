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

class V2Dot2Dot5 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-01-16 10:00:00');
    }

    public function up(): void
    {
        $this->getConnection()->createQueryBuilder()
            ->update('matching_rule')
            ->set('type', ':similar')
            ->where('type=:like')
            ->setParameter('similar', 'similar')
            ->setParameter('like', 'like')
            ->executeQuery();
    }
}
