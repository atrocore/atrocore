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

class UpdateCurrencyExchangeViaECB extends Base
{
    public function run($scheduledJobId): bool
    {
        $scheduledJob = $this->getEntityManager()->getEntity('ScheduledJob', $scheduledJobId);

        if (empty($scheduledJob)) {
            return true;
        }

        $currencyRates = $this->getConfig()->get('currencyRates');
        $baseCurrency = $this->getConfig()->get('baseCurrency');

        if (empty($baseCurrency) || empty($currencyRates)) {
            return true;
        }

        $ecbRates = self::getExchangeRates();
        $ecbRates['EUR'] = 1;
        if (empty($ecbRates) || empty($ecbRates[$baseCurrency])) {
            return true;
        }

        foreach ($currencyRates as $rateKey => $rateValue) {
            if (array_key_exists($rateKey, $ecbRates) && !empty($ecbRates[$rateKey])) {
                $currencyRates[$rateKey] = $ecbRates[$baseCurrency] / $ecbRates[$rateKey];
            }
        }

        $units = $this->getEntityManager()->getRepository('Unit')->where(['measureId' => 'currency'])->find();
        foreach ($units as $unit) {
            if (!empty($currencyRates[$unit['name']])) {
                $unit->set('multiplier', $currencyRates[$unit['name']]);
                $this->getEntityManager()->saveEntity($unit);
            }
        }

        $this->getConfig()->set('currencyRates', $currencyRates);
        $this->getConfig()->save();

        return true;
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