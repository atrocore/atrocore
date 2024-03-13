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

namespace Atro\Core\ModuleManager;

use Atro\Core\Container;

/**
 * Class AfterInstallAfterDelete
 */
class AfterInstallAfterDelete
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * AbstractEvent constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * After module install event
     */
    public function afterInstall(): void
    {
    }

    /**
     * After module delete event
     */
    public function afterDelete(): void
    {
    }

    /**
     * @return Container
     */
    protected function getContainer(): Container
    {
        return $this->container;
    }
}
