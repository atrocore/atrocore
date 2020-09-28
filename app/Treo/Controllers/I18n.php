<?php

declare(strict_types=1);

namespace Treo\Controllers;

/**
 * Controller I18n
 *
 * @author r.ratsun r.ratsun@zinitsolutions.com
 */
class I18n extends \Espo\Controllers\I18n
{
    /**
     * Read action
     *
     * @param array $params
     * @param array $data
     * @param mixed $request
     *
     * @return mixed
     */
    public function actionRead($params, $data, $request)
    {
        if (!empty($locale = $request->get('locale'))) {
            // set locale
            $this
                ->getContainer()
                ->get('language')
                ->setLanguage($locale);
        }

        return parent::actionRead($params, $data, $request);
    }
}
