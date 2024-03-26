<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Jobs;

use Espo\Core\Jobs\Base;
use Espo\Services\Composer;

/**
 * Class ComposerAutoUpdate
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
