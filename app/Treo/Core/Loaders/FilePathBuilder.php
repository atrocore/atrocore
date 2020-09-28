<?php
declare(strict_types=1);

namespace Treo\Core\Loaders;

/**
 * Class FilePathBuilder
 *
 * @package Treo\Core\Loaders
 */
class FilePathBuilder extends Base
{
    /**
     * @return \Treo\Core\FilePathBuilder
     */
    public function load()
    {
        return new \Treo\Core\FilePathBuilder($this->getContainer());
    }
}
