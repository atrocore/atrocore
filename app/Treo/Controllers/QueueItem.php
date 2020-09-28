<?php
declare(strict_types=1);

namespace Treo\Controllers;

use Espo\Core\Exceptions\NotFound;

/**
 * Class QueueItem
 *
 * @author r.ratsun@zinitsolutions.com
 */
class QueueItem extends \Espo\Core\Templates\Controllers\Base
{
    /**
     * @inheritdoc
     */
    public function actionCreate($params, $data, $request)
    {
        throw new NotFound();
    }
}
