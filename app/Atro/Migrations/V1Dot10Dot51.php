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
use Espo\Core\Utils\Util;

class V1Dot10Dot51 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-08-01 15:00:00');
    }

    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if (!$toSchema->hasTable('country')) {
            $table = $toSchema->createTable('country');
            $table->addColumn('id', 'string', ['length' => 24]);
            $table->addColumn('name', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('deleted', 'boolean', ['default' => false, 'notnull' => false]);
            $table->addColumn('code', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('is_active', 'boolean', ['default' => false]);
            $table->addColumn('eu', 'boolean', ['default' => false]);

            $table->addUniqueIndex(['code', 'deleted'], 'UNIQ_5373C96677153098EB3B4E33');

            $table->addIndex(['name', 'deleted'], 'IDX_COUNTRY_NAME');

            $table->setPrimaryKey(['id']);

            foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                $this->exec($sql);
            }

            /** @var \Espo\Core\Utils\Metadata $metadata */
            $metadata = (new \Atro\Core\Application())->getContainer()->get('metadata');

            $qb = $this->getConnection()->createQueryBuilder();

            foreach ($metadata->get(['app', 'defaultCountriesList'], []) as $country) {
                if (isset($country['name'])) {
                    $qb
                        ->insert('country')
                        ->setValue('id', ':id')
                        ->setValue('name', ':name')
                        ->setValue('code', ':code')
                        ->setValue('deleted', ':false')
                        ->setValue('is_active', ':true')
                        ->setValue('eu', ':eu')
                        ->setParameter('id', !empty($country['code']) ? strtolower($country['code']) : Util::generateId())
                        ->setParameter('name', $country['name'])
                        ->setParameter('code', $country['code'] ?? null)
                        ->setParameter('false', false, ParameterType::BOOLEAN)
                        ->setParameter('true', true, ParameterType::BOOLEAN)
                        ->setParameter('eu', $country['eu'] ?? false, ParameterType::BOOLEAN)
                        ->executeStatement();
                }
            }
        }

        if ($toSchema->hasTable('address')) {
            $table = $toSchema->getTable('address');

            if ($table->hasColumn('country')) {
                $table->dropColumn('country');
            }

            if ($table->hasColumn('country_code')) {
                $table->dropColumn('country_code');
            }

            if (!$table->hasColumn('country_id')) {
                $table->addColumn('country_id', 'string', ['length' => 24, 'notnull' => false]);
                $table->addIndex(['country_id', 'deleted'], 'IDX_ADDRESS_COUNTRY_ID');
            }

            foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                $this->exec($sql);
            }
        }

        $this->updateComposer('atrocore/core', '^1.10.51');
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
