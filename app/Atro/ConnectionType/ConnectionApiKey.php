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

namespace Atro\ConnectionType;

use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class ConnectionApiKey extends ConnectionHttp implements ConnectionInterface
{
    public function connect(Entity $connectionEntity)
    {
        throw new BadRequest($this->exception('apiKeyTestNotSupported'));
    }

    protected function getHeaders(): array
    {
        $value = $this->decryptPassword((string)$this->connectionEntity->get('apiKeyValue'));
        if ($value === false) {
            throw new BadRequest(sprintf($this->exception('connectionFailed'), $this->exception('apiKeyValueIsEmpty')));
        }

        return ["{$this->connectionEntity->get('apiKeyName')}: $value"];
    }
}
