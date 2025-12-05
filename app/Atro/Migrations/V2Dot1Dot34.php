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
use Atro\ORM\DB\RDB\Mapper;

class V2Dot1Dot34 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-12-05 12:00:00');
    }

    public function up(): void
    {
        $data = [
            'id'             => 'SendReports',
            'name'           => 'Send anonymous error reports to AtroCore',
            'type'           => 'SendReports',
            'is_active'      => $this->getConfig()->get('reportingEnabled', false),
            'scheduling'     => '*/15 * * * *',
            'created_at'     => date('Y-m-d H:i:s'),
            'modified_at'    => date('Y-m-d H:i:s'),
            'created_by_id'  => 'system',
            'modified_by_id' => 'system',
        ];

        $qb = $this->getConnection()->createQueryBuilder();
        $qb->insert('scheduled_job');

        foreach ($data as $columnName => $value) {
            $qb->setValue($columnName, ":$columnName");
            $qb->setParameter($columnName, $value, Mapper::getParameterType($value));
        }

        try {
            $qb->executeQuery();
        } catch (\Throwable $e) {
        }
    }
}
