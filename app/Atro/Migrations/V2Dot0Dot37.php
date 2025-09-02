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
use Atro\Core\Utils\Util;

class V2Dot0Dot37 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-08-30 18:00:00');
    }

    public function up(): void
    {
        $this->getConnection()->createQueryBuilder()
            ->update('notification_template')
            ->set('data', 'REPLACE(data, :oldVal, :newVal)')
            ->where('id = :id')
            ->setParameter('oldVal', "{{ translate(field, context.language, 'fields', context.entityType) }}")
            ->setParameter('newVal', "{{ context.updateData['fieldDefs'][field]['label'] ?? translate(field, context.language, 'fields', context.entityType) }}")
            ->setParameter('id', 'systemUpdateEntity')
            ->executeStatement();
    }
}
