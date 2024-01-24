<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\ConnectionType;

use Atro\Core\Container;

abstract class AbstractConnection implements ConnectionInterface
{
    protected Container $container;
    protected array $data;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    protected function decryptPassword(string $hash): string
    {
        return $this->container->get('serviceFactory')->create('Connection')->decryptPassword($hash);
    }

    protected function exception(string $name, string $scope = 'Connection'): string
    {
        return $this->container->get('language')->translate($name, 'exceptions', 'Connection');
    }

    public function setData(array $data) {
        $this->data = $data;
    }
}
