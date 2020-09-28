<?php

declare(strict_types=1);

namespace Treo\Core\Loaders;

use Treo\Core\Utils\Api\Slim as Instance;

/**
 * Slim loader
 *
 * @author r.ratsun@zinitsolutions.com
 */
class Slim extends Base
{

    /**
     * Load Slim
     *
     * @return Instance
     */
    public function load()
    {
        return new Instance();
    }
}
