<?php

declare(strict_types=1);

namespace Treo\Controllers;

/**
 * Class TreoStore
 *
 * @author r.ratsun@treolabs.com
 */
class TreoStore extends \Espo\Core\Templates\Controllers\Base
{
    /**
     * @inheritdoc
     */
    protected function fetchListParamsFromRequest(&$params, $request, $data)
    {
        // parent
        parent::fetchListParamsFromRequest($params, $request, $data);

        // set isInstalled
        if (!is_null($request->get('isInstalled'))) {
            $params['isInstalled'] = ($request->get('isInstalled') === 'true');
        }
    }
}
