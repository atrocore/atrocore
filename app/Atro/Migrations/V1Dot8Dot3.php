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
use Espo\Core\Utils\Util;
use Espo\Jobs\UpdateCurrencyExchangeViaECB;

class V1Dot8Dot3 extends Base
{
    public function up(): void
    {
        $this->getConnection()->createQueryBuilder()
            ->insert('measure')
            ->values([
                'name' => '?',
                'id'   => '?',
                'code' => '?'
            ])
            ->setParameter(0, 'Currency')
            ->setParameter(1, 'currency')
            ->setParameter(2, 'currency')
            ->executeStatement();

        $currencies = ["EUR", "USD", "CHF", "GBP"];
        $rates = UpdateCurrencyExchangeViaECB::getExchangeRates();
        foreach ($currencies as $currency) {
            $this->getConnection()->createQueryBuilder()
                ->insert('unit')
                ->values([
                    'id'         => '?',
                    'name'       => '?',
                    'measure_id' => '?',
                    'is_default' => '?',
                    'multiplier' => '?',
                    'code'       => '?'
                ])
                ->setParameter(0, Util::generateId())
                ->setParameter(1, $currency)
                ->setParameter(2, 'currency')
                ->setParameter(3, $currency === 'EUR', ParameterType::BOOLEAN)
                ->setParameter(4, $currency === 'EUR' ? 1 : $rates[$currency])
                ->setParameter(5, $currency)
                ->executeStatement();
        }
    }

    public function down(): void
    {
    }
}
