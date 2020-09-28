<?php
declare(strict_types=1);

namespace Treo\Core\Loaders;

/**
 * DataManager loader
 *
 * @author r.ratsun@gmail.com
 */
class DataManager extends Base
{
    /**
     * @inheritdoc
     */
    public function load()
    {
        return new \Espo\Core\DataManager($this->getContainer());
    }
}
