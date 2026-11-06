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

namespace Atro\Core\Dashlets;

/**
 * Provides the data of a dashlet, available via `GET /api/Dashlet/{name}`. The class is set as `className` in the
 * metadata of the dashlet (`metadata/dashlets/{Name}.json`) and is obtained from the container, so its dependencies
 * are injected via the constructor. A dashlet is not bound to an entity: it checks the access to the data it reads itself.
 */
interface DashletInterface
{
    /**
     * @param array $params The query parameters of the request; `where` is already decoded.
     */
    public function getData(array $params): array;
}
