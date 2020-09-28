<?php

declare(strict_types=1);

namespace Treo\Controllers;

use Espo\Controllers\Admin as Base;
use Espo\Core\Exceptions\NotFound;

/**
 * Controller Admin
 *
 * @author r.ratsun r.ratsun@gmail.com
 */
class Admin extends Base
{
    /**
     * @throws NotFound
     */
    public function actionNotFound()
    {
        throw new NotFound();
    }
}
