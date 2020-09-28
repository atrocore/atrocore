<?php

namespace Espo\Core\Utils\Database\Schema\rebuildActions;

class Currency extends \Espo\Core\Utils\Database\Schema\BaseRebuildActions
{

    public function afterRebuild()
    {
        $defaultCurrency = $this->getConfig()->get('defaultCurrency');

        $baseCurrency = $this->getConfig()->get('baseCurrency');
        $currencyRates = $this->getConfig()->get('currencyRates');

        if ($defaultCurrency != $baseCurrency) {
            $currencyRates = $this->exchangeRates($baseCurrency, $defaultCurrency, $currencyRates);
        }

        $currencyRates[$defaultCurrency] = '1.00';

        $pdo = $this->getEntityManager()->getPDO();

        $sql = "TRUNCATE `currency`";
        $pdo->prepare($sql)->execute();

        foreach ($currencyRates as $currencyName => $rate) {

            $sql = "
                INSERT INTO `currency`
                (id, rate)
                VALUES
                (".$pdo->quote($currencyName) . ", " . $pdo->quote($rate) . ")
            ";
            $pdo->prepare($sql)->execute();
        }
    }

    /**
     * Calculate exchange rates if defaultCurrency doesn't equals baseCurrency
     *
     * @param  string $baseCurrency
     * @param  string $defaultCurrency
     * @param  array $currencyRates   [description]
     * @return array  - List of new currency rates
     */
    protected function exchangeRates($baseCurrency, $defaultCurrency, array $currencyRates)
    {
        $precision = 5;
        $defaultCurrencyRate = round(1 / $currencyRates[$defaultCurrency], $precision);

        $exchangedRates = array();
        $exchangedRates[$baseCurrency] = $defaultCurrencyRate;

        unset($currencyRates[$baseCurrency], $currencyRates[$defaultCurrency]);

        foreach ($currencyRates as $currencyName => $rate) {
            $exchangedRates[$currencyName] = round($rate * $defaultCurrencyRate, $precision);
        }

        return $exchangedRates;
    }

}

