<?php

declare(strict_types=1);

namespace Treo\Controllers;

/**
 * Controller Preferences
 *
 * @author r.ratsun r.ratsun@zinitsolutions.com
 */
class Preferences extends \Espo\Controllers\Preferences
{
    /**
     * Read action
     *
     * @param array $params
     *
     * @return array
     */
    public function actionRead($params)
    {
        // get result
        $result = parent::actionRead($params);

        // prepare defaultCurrency
        $result->defaultCurrency = null;

        return $result;
    }
}
