<?php

declare(strict_types=1);

namespace Treo\Core\Loaders;

use Espo\Core\Utils\File\ClassParser as Instance;

/**
 * Class ClassParser
 *
 * @author r.ratsun@treolabs.com
 */
class ClassParser extends Base
{
    /**
     * Load ClassParser
     *
     * @return Instance
     */
    public function load()
    {
        return new Instance(
            $this->getContainer()->get('fileManager'),
            $this->getContainer()->get('config'),
            $this->getContainer()->get('metadata')
        );
    }
}
