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

use Atro\Core\Exceptions\Error;
use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;

class V1Dot10Dot0 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-03-18');
    }

    public function up(): void
    {
        $res = $this->getConnection()->createQueryBuilder()
            ->select('*')
            ->from('asset_type')
            ->fetchAllAssociative();

        foreach ($res as $v) {
            $this->getConnection()->createQueryBuilder()
                ->delete('asset_type')
                ->where('id = :id')
                ->setParameter('id', $v['id'])
                ->executeQuery();

            $this->getConnection()->createQueryBuilder()
                ->insert('file_type')
                ->setValue('id', ':id')
                ->setValue('name', ':name')
                ->setValue('assign_automatically', ':assignAutomatically')
                ->setValue('sort_order', ':sortOrder')
                ->setValue('created_by_id', ':createdById')
                ->setValue('modified_by_id', ':modifiedById')
                ->setValue('sort_order', ':sortOrder')
                ->setParameter('id', $v['id'])
                ->setParameter('name', $v['name'])
                ->setParameter('assignAutomatically', !empty($v['assign_automatically']), ParameterType::BOOLEAN)
                ->setParameter('sortOrder', $v['sort_order'])
                ->setParameter('createdById', $v['created_by_id'])
                ->setParameter('modifiedById', $v['modified_by_id'])
                ->executeQuery();
        }

        $this->getConnection()->createQueryBuilder()
            ->update('validation_rule')
            ->set('file_type_id', 'asset_type_id')
            ->where('deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->executeQuery();

        $this->getConfig()->remove('whitelistedExtensions');
        $this->getConfig()->save();

        $this->updateComposer('atrocore/core', '^1.10.0');
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited.');
    }
}
