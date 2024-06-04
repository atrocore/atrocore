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

use Atro\DTO\HttpResponseDTO;

interface HttpConnectionInterface
{
    public function request(string $url, string $method = 'GET', array $headers = [], string $body = null, bool $validate = true): HttpResponseDTO;

    public function generateUrlForEntity(string $entityName): string;
}
