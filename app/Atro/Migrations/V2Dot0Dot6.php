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

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\ParameterType;

class V2Dot0Dot6 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-06-12 15:00:00');
    }

    public function up(): void
    {
//        if ($this->isPgSQL()) {
//            $this->exec("");
//        } else {
//            $this->exec("");
//        }

        $roles = $this->getConnection()->createQueryBuilder()
            ->select('*')
            ->from($this->getConnection()->quoteIdentifier('role'))
            ->where('deleted=:false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($roles as $role) {
            $data = @json_decode((string)$role['data'], true);
            if (!is_array($data)) {
                $data = [];
            }

            foreach ($data as $scope => $row) {
                $this->getConnection()->createQueryBuilder()
                    ->insert('role_scope')
                    ->setValue('id', ':id')
                    ->setValue('role_id', ':roleId')
                    ->setValue('name', ':scope')
                    ->setValue('has_access', ':hasAccess')
                    ->setValue('create_action', ':createAction')
                    ->setValue('read_action', ':readAction')
                    ->setValue('edit_action', ':editAction')
                    ->setValue('delete_action', ':deleteAction')
                    ->setValue('stream_action', ':streamAction')
                    ->setValue('created_at', ':date')
                    ->setValue('modified_at', ':date')
                    ->setValue('created_by_id', ':system')
                    ->setValue('modified_by_id', ':system')
                    ->setParameter('id', Util::generateId())
                    ->setParameter('roleId', $role['id'])
                    ->setParameter('scope', $scope)
                    ->setParameter('hasAccess', !empty($row), ParameterType::BOOLEAN)
                    ->setParameter('createAction', !empty($row['create']) && $row['create'] === 'yes', ParameterType::BOOLEAN)
                    ->setParameter('readAction', !empty($row['read']) ? $row['read'] : 'no')
                    ->setParameter('editAction', !empty($row['edit']) ? $row['edit'] : 'no')
                    ->setParameter('deleteAction', !empty($row['delete']) ? $row['delete'] : 'no')
                    ->setParameter('streamAction', !empty($row['stream']) ? $row['stream'] : 'no')
                    ->setParameter('date', date('Y-m-d H:i:s'))
                    ->setParameter('system', 'system')
                    ->executeQuery();
            }
        }
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
