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

namespace Atro\Jobs;

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
            if (!empty($unit->get('isMain'))) {
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