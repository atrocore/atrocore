<?php
declare(strict_types=1);

namespace Treo\Core\Loaders;

use Espo\Core\Utils\FieldManagerUtil as Instance;
use Treo\Core\Utils\Metadata;

/**
 * FieldManagerUtil loader
 *
 * @author r.ratsun@zinitsolutions.com
 */
class FieldManagerUtil extends Base
{

    /**
     * Load FieldManagerUtil
     *
     * @return \Espo\Core\Utils\FieldManagerUtil
     */
    public function load()
    {
        return new Instance($this->getMetadata());
    }

    /**
     * Get metadata
     *
     * @return Metadata
     */
    protected function getMetadata()
    {
        return $this->getContainer()->get('metadata');
    }
}
