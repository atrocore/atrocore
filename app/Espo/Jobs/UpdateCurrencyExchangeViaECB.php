<?php
/*
 * This file is part of premium software, which is NOT free.
 * Copyright (c) AtroCore UG (haftungsbeschränkt).
 *
 * This Software is the property of AtroCore UG (haftungsbeschränkt) and is
 * protected by copyright law - it is NOT Freeware and can be used only in one
 * project under a proprietary license, which is delivered along with this program.
 * If not, see <https://atropim.com/eula> or <https://atrodam.com/eula>.
 *
 * This Software is distributed as is, with LIMITED WARRANTY AND LIABILITY.
 * Any unauthorised use of this Software without a valid license is
 * a violation of the License Agreement.
 *
 * According to the terms of the license you shall not resell, sublicense,
 * rent, lease, distribute or otherwise transfer rights or usage of this
 * Software or its derivatives. You may modify the code of this Software
 * for your own needs, if source code is provided.
 */

declare(strict_types=1);

namespace Espo\Jobs;

use Espo\Core\Jobs\Base;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class UpdateCurrencyExchangeViaECB extends Base
{
    public function run($scheduledJobId): bool
    {
        $scheduledJob = $this->getEntityManager()->getEntity('ScheduledJob', $scheduledJobId);

        if (empty($scheduledJob)) {
            return true;
        }

        $this->updateCurrencyRates();

        return true;
    }

    public function updateCurrencyRates(Entity $toUpdateUnit = null): void
    {
        $units = $this->getEntityManager()->getRepository('Unit')
            ->where(['measureId' => 'currency'])
            ->find();

        foreach ($units as $unit) {
            if (!empty($unit->get('isDefault'))) {
                $baseCurrency = $unit->get('code');
            }
        }

        $ecbRates = self::getExchangeRates();
        $ecbRates['EUR'] = 1;

        if (empty($baseCurrency) || empty($ecbRates) || empty($ecbRates[$baseCurrency])) {
            return;
        }

        $toUpdateUnits = empty($toUpdateUnit) ? $units : new EntityCollection([$toUpdateUnit], 'Unit');

        foreach ($toUpdateUnits as $unit) {
            if (!isset($ecbRates[$unit->get('code')])) {
                continue;
            }
            $unit->set('multiplier', round($ecbRates[$baseCurrency] / $ecbRates[$unit->get('code')], 4));

            $this->getEntityManager()->saveEntity($unit);
        }
    }

    public static function getExchangeRates(): array
    {
        $xml = @file_get_contents('https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml', false);

        if (empty($xml)) {
            return [];
        }

        $data = @simplexml_load_string((string)$xml);
        if (empty($data)) {
            return [];
        }

        $data = @json_decode(@json_encode($data), true);

        $rates = [];
        if (!empty($data['Cube']['Cube']['Cube']) && is_array($data['Cube']['Cube']['Cube'])) {
            foreach ($data['Cube']['Cube']['Cube'] as $cube) {
                if (!empty($cube['@attributes']['currency']) && !empty($cube['@attributes']['rate'])) {
                    $rates[(string)$cube['@attributes']['currency']] = (float)$cube['@attributes']['rate'];
                }
            }
        }

        return $rates;
    }
}