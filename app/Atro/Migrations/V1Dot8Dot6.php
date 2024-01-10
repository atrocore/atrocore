<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Exceptions\Error;
use Espo\Core\Utils\Util;
use Espo\Jobs\UpdateCurrencyExchangeViaECB;

class V1Dot8Dot6 extends Base
{
    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $this->addColumn($toSchema, 'measure', 'display_format', ['type' => 'string', 'default' => null]);
        $this->addColumn($toSchema, 'unit', 'symbol', ['type' => 'string', 'default' => null]);

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getPDO()->exec($sql);
        }

        try {
            if (!$toSchema->hasTable('user_followed_record')) {
                $this->getPDO()->exec('alter table subscription rename to user_followed_record;');
            }

            $this->getConnection()->createQueryBuilder()
                ->insert('measure')
                ->values([
                    'name' => '?',
                    'id'   => '?',
                    'code' => '?',
                    'display_format' => '?'
                ])
                ->setParameter(0, 'Currency')
                ->setParameter(1, 'currency')
                ->setParameter(2, 'currency')
                ->setParameter(3, '2')
                ->executeStatement();

            $symbols = ["EUR" => "€", "USD" => "$", "CHF" => "Fr.", "GBP" => "£"];

            $rates = UpdateCurrencyExchangeViaECB::getExchangeRates();
            foreach ($symbols as $currency => $symbol) {
                $this->getConnection()->createQueryBuilder()
                    ->insert('unit')
                    ->values([
                        'id'         => '?',
                        'name'       => '?',
                        'measure_id' => '?',
                        'is_default' => '?',
                        'multiplier' => '?',
                        'code'       => '?',
                        'symbol'     => '?'
                    ])
                    ->setParameter(0, $currency)
                    ->setParameter(1, $currency)
                    ->setParameter(2, 'currency')
                    ->setParameter(3, $currency === 'EUR', ParameterType::BOOLEAN)
                    ->setParameter(4, $currency === 'EUR' ? 1 : $rates[$currency])
                    ->setParameter(5, $currency)
                    ->setParameter(6, $symbol)
                    ->executeStatement();
            }
        } catch (\Exception $exception) {

        }


        /** @var \Espo\Core\Utils\Metadata $metadata */
        $metadata = (new \Atro\Core\Application())->getContainer()->get('metadata');

        $dir = "custom/Espo/Custom/Resources/metadata/entityDefs";
        $files = scandir($dir);
        foreach ($files as $file) {
            if (in_array($file, array(".", ".."))) {
                continue;
            }
            $entity = explode(".", $file)[0];
            $data = $metadata->getCustom("entityDefs", $entity);
            foreach ($data['fields'] as $field => $fieldDef) {
                $type = $fieldDef['type'];
                if (!empty($fieldDef['isCustom']) && in_array($type, ['currency', 'rangeCurrency'])) {
                    try {
                        V1Dot8Dot3::migrateCurrencyField($this, $entity, $field, $type);
                    } catch (\Exception $exception) {
                        $a = 0;
                    }
                    $data['fields'][$field]['type'] = $type === 'rangeCurrency' ? 'rangeFloat' : 'float';
                    $data['fields'][$field]['measureId'] = "currency";
                }
            }
            $metadata->saveCustom('entityDefs', $entity, $data);
        }

        // create scheduled job
        $this->getConnection()->createQueryBuilder()
            ->insert('scheduled_job')
            ->values([
                'id'             => '?',
                'name'           => '?',
                'job'            => '?',
                'scheduling'     => '?',
                'created_at'     => '?',
                'modified_at'    => '?',
                'created_by_id'  => '?',
                'modified_by_id' => '?',
                'is_internal'    => '?',
                'status'         => '?'
            ])
            ->setParameter(0, Util::generateId())
            ->setParameter(1, 'UpdateCurrencyExchangeViaECB')
            ->setParameter(2, 'UpdateCurrencyExchangeViaECB')
            ->setParameter(3, '0 2 * * *')
            ->setParameter(4, date('Y-m-d H:i:s'))
            ->setParameter(5, date('Y-m-d H:i:s'))
            ->setParameter(6, 'system')
            ->setParameter(7, 'system')
            ->setParameter(8, true, ParameterType::BOOLEAN)
            ->setParameter(9, 'Active')
            ->executeStatement();

        $this->updateComposer('atrocore/core', '^1.8.3');
    }

    public function down(): void
    {
        throw new Error("Downgrade prohibited");
    }

}
