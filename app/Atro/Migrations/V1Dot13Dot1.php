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
use Doctrine\DBAL\ParameterType;

class V1Dot13Dot1 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-01-23 12:00:00');
    }

    public function up(): void
    {
        try {
            $res = $this->getConnection()->createQueryBuilder()
                ->select('id')
                ->from('layout_profile')
                ->fetchOne();

            if (!empty($res)) {
                return;
            }

            // create default profile
            $this->getConnection()->createQueryBuilder()
                ->insert('layout_profile')
                ->values([
                    'id'         => ':id',
                    'name'       => ':name',
                    'is_active'  => ':true',
                    'is_default' => ':true',
                ])->setParameters([
                    'id'   => 'standard',
                    'name' => 'Standard'
                ])
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->executeStatement();

        } catch (\Throwable $e) {

        }
    }

    public function down(): void
    {

    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
