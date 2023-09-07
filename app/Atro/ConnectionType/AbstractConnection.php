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

use Espo\Core\Injectable;

abstract class AbstractConnection extends Injectable implements ConnectionInterface
{
    public function __construct()
    {
        $this->addDependencyList(['config', 'entityManager', 'user', 'language', 'serviceFactory']);
    }

    protected function decryptPassword(string $hash): string
    {
        return $this->getInjection('serviceFactory')->create('Connection')->decryptPassword($hash);
    }

    protected function exception(string $name, string $scope = 'Connection'): string
    {
        return $this->getInjection('language')->translate($name, 'exceptions', 'Connection');
    }
}
