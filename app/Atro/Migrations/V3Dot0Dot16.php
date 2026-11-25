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

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;

class V3Dot0Dot16 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-11-25 20:00:00');
    }

    /**
     * The usage limit and the expiry date of a sharing apply when their flags are on.
     */
    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $table = $toSchema->getTable('sharing');
        foreach (['limit_usage', 'expires'] as $column) {
            if (!$table->hasColumn($column)) {
                $table->addColumn($column, 'boolean', ['notnull' => true, 'default' => false]);
            }
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getDbal()->executeStatement($sql);
        }

        // a usage limit of zero meant no limit
        $conditions = ['limit_usage' => 'allowed_usage > 0', 'expires' => 'valid_till IS NOT NULL'];
        foreach ($conditions as $flagColumn => $condition) {
            $this->getDbal()->createQueryBuilder()
                ->update('sharing')
                ->set($flagColumn, ':true')
                ->where($condition)
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->executeStatement();
        }

        $this->getDbal()->createQueryBuilder()
            ->update('sharing')
            ->set('used', '0')
            ->where('used IS NULL')
            ->executeStatement();
    }
}
