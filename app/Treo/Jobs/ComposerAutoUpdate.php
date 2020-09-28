<?php

declare(strict_types=1);

namespace Treo\Jobs;

use Espo\Core\Jobs\Base;
use Treo\Services\Composer;

/**
 * Class ComposerAutoUpdate
 *
 * @author r.ratsun@treolabs.com
 */
class ComposerAutoUpdate extends Base
{
    /**
     * Run job
     *
     * @return bool
     */
    public function run()
    {
        // cancel changes
        $this->getComposerService()->cancelChanges();

        return $this->getComposerService()->runUpdate();
    }

    /**
     * @return Composer
     */
    protected function getComposerService(): Composer
    {
        return $this->getServiceFactory()->create('Composer');
    }
}
