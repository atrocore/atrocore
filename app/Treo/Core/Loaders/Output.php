<?php

declare(strict_types=1);

namespace Treo\Core\Loaders;

use Espo\Core\Utils\Api\Slim;

/**
 * Output loader
 *
 * @author r.ratsun@zinitsolutions.com
 */
class Output extends Base
{

    /**
     * Load Output
     *
     * @return \Espo\Core\Utils\Api\Output
     */
    public function load()
    {
        return new \Espo\Core\Utils\Api\Output($this->getSlim());
    }

    /**
     * Get slim
     *
     * @return Slim
     */
    protected function getSlim()
    {
        return $this->getContainer()->get('slim');
    }
}
