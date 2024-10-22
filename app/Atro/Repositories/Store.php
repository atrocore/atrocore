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

namespace Atro\Repositories;

use Atro\Core\Templates\Repositories\ReferenceData;
use Espo\ORM\Entity;

class Store extends ReferenceData
{
    public function insertEntity(Entity $entity): bool
    {
        return false;
    }

    public function updateEntity(Entity $entity): bool
    {
        return false;
    }

    public function deleteEntity(Entity $entity): bool
    {
        return false;
    }

    protected function getAllItems(array $params = []): array
    {
        $items = [];

        $items['atrocore/core'] = [
            'id'             => 'core',
            'name'           => 'Core',
            'code'           => 'atrocore/core',
            'description'    => "",
            'url'            => 'git@gitlab.atrocore.com:atrocore/amazon-adapter.git',
            'status'         => 'installed',
            'usage'          => 'public',
            'expirationDate' => null,
            'versions'       => [],
            'tags'           => []
        ];

        $items['atrocore/workflows'] = [
            'id'             => 'Workflows',
            'name'           => 'Workflows',
            'code'           => 'atrocore/workflows',
            'description'    => "This module allows you to configure and manage different workflows and their automations. A workflow can include events, conditions of any complexity, states and actions.",
            'url'            => 'git@gitlab.atrocore.com:atrocore/workflows.git',
            'status'         => 'available',
            'usage'          => 'rent',
            'expirationDate' => '2024-10-25',
            'versions'       => json_decode('[{"version":"1.3.29","require":{"atrocore\/core":"~1.11.0","twig\/twig":"^3.4"}},{"version":"1.3.28","require":{"atrocore\/core":"~1.10.60","twig\/twig":"^3.4"}},{"version":"1.3.27","require":{"atrocore\/core":"~1.10.60","twig\/twig":"^3.4"}},{"version":"1.3.26","require":{"atrocore\/core":"~1.10.60","twig\/twig":"^3.4"}},{"version":"1.3.25","require":{"atrocore\/core":"~1.10.60","twig\/twig":"^3.4"}},{"version":"1.3.24","require":{"atrocore\/core":"~1.10.60","twig\/twig":"^3.4"}},{"version":"1.3.23","require":{"atrocore\/core":"~1.10.52","twig\/twig":"^3.4"}},{"version":"1.3.22","require":{"atrocore\/core":"~1.10.47","twig\/twig":"^3.4"}},{"version":"1.3.21","require":{"atrocore\/core":"~1.10.47","twig\/twig":"^3.4"}},{"version":"1.3.20","require":{"atrocore\/core":"~1.10.41","twig\/twig":"^3.4"}},{"version":"1.3.19","require":{"atrocore\/core":"~1.10.16","twig\/twig":"^3.4"}},{"version":"1.3.18","require":{"atrocore\/core":"~1.10.16","twig\/twig":"^3.4"}},{"version":"1.3.17","require":{"atrocore\/core":"~1.10.16","twig\/twig":"^3.4"}},{"version":"1.3.16","require":{"atrocore\/core":"~1.10.16","twig\/twig":"^3.4"}},{"version":"1.3.15","require":{"atrocore\/core":"~1.10.16","twig\/twig":"^3.4"}},{"version":"1.3.14","require":{"atrocore\/core":">=1.8.28 <1.11.0","twig\/twig":"^3.4"}},{"version":"1.3.13","require":{"atrocore\/core":">=1.8.28 <1.10.0","twig\/twig":"^3.4"}},{"version":"1.3.12","require":{"atrocore\/core":"~1.8.28","twig\/twig":"^3.4"}},{"version":"1.3.11","require":{"atrocore\/core":"~1.8.28","twig\/twig":"^3.4"}},{"version":"1.3.10","require":{"atrocore\/core":"~1.8.28","twig\/twig":"^3.4"}},{"version":"1.3.9","require":{"atrocore\/core":"~1.8.28","twig\/twig":"^3.4"}},{"version":"1.3.8","require":{"atrocore\/core":"~1.8.12","twig\/twig":"^3.4"}},{"version":"1.3.7","require":{"atrocore\/core":"~1.8.12","twig\/twig":"^3.4"}},{"version":"1.3.6","require":{"atrocore\/core":"~1.8.12","twig\/twig":"^3.4"}},{"version":"1.3.5","require":{"atrocore\/core":"~1.8.12","twig\/twig":"^3.4"}},{"version":"1.3.4","require":{"atrocore\/core":"~1.8.12","twig\/twig":"^3.4"}},{"version":"1.3.3","require":{"atrocore\/core":"~1.8.12","twig\/twig":"^3.4"}},{"version":"1.3.2","require":{"atrocore\/core":"~1.8.8","twig\/twig":"^3.4"}},{"version":"1.3.1","require":{"atrocore\/core":"~1.8.8","twig\/twig":"^3.4"}},{"version":"1.3.0","require":{"atrocore\/core":"~1.8.8","twig\/twig":"^3.4"}},{"version":"1.2.21","require":{"atrocore\/core":">=1.7.24 <1.9.0","twig\/twig":"^3.4"}},{"version":"1.2.20","require":{"atrocore\/core":">=1.7.24 < 1.8.0","twig\/twig":"^3.4"}},{"version":"1.2.19","require":{"atrocore\/core":">=1.6.48 < 1.8.0","twig\/twig":"^3.4"}},{"version":"1.2.18","require":{"atrocore\/core":">=1.6.48 < 1.8.0","twig\/twig":"^3.4"}},{"version":"1.2.17","require":{"atrocore\/core":">=1.6.48 < 1.8.0","twig\/twig":"^3.4"}},{"version":"1.2.16","require":{"atrocore\/core":">=1.6.48 < 1.8.0","twig\/twig":"^3.4"}},{"version":"1.2.15","require":{"atrocore\/core":">=1.6.48 < 1.8.0","twig\/twig":"^3.4"}},{"version":"1.2.14","require":{"atrocore\/core":">=1.6.48 < 1.8.0","twig\/twig":"^3.4"}},{"version":"1.2.13","require":{"atrocore\/core":"~1.6.48","twig\/twig":"^3.4"}},{"version":"1.2.12","require":{"atrocore\/core":"~1.6.48","twig\/twig":"^3.4"}},{"version":"1.2.11","require":{"atrocore\/core":"~1.6.48","twig\/twig":"^3.4"}},{"version":"1.2.10","require":{"atrocore\/core":"~1.6.48","twig\/twig":"^3.4"}},{"version":"1.2.9","require":{"atrocore\/core":"~1.6.48","twig\/twig":"^3.4"}},{"version":"1.2.8","require":{"atrocore\/core":"~1.6.48","twig\/twig":"^3.4"}},{"version":"1.2.7","require":{"atrocore\/core":"~1.6.46","twig\/twig":"^3.4"}},{"version":"1.2.6","require":{"atrocore\/core":"~1.6.21","twig\/twig":"^3.4"}},{"version":"1.2.5","require":{"atrocore\/core":"~1.6.21","twig\/twig":"^3.4"}},{"version":"1.2.4","require":{"atrocore\/core":"~1.6.21","twig\/twig":"^3.4"}},{"version":"1.2.3","require":{"atrocore\/core":"~1.6.21","twig\/twig":"^3.4"}},{"version":"1.2.2","require":{"atrocore\/core":"~1.6.21","twig\/twig":"^3.4"}},{"version":"1.2.1","require":{"atrocore\/core":"~1.6.21","twig\/twig":"^3.4"}},{"version":"1.2.0","require":{"atrocore\/core":"~1.6.21","twig\/twig":"^3.4"}},{"version":"1.1.11","require":{"atrocore\/core":">1.4.146 <1.7.0","twig\/twig":"^3.4"}},{"version":"1.1.10","require":{"atrocore\/core":">1.4.146 <1.7.0","twig\/twig":"^3.4"}},{"version":"1.1.9","require":{"atrocore\/core":">1.4.146 <1.7.0","twig\/twig":"^3.4"}},{"version":"1.1.8","require":{"atrocore\/core":">1.4.146 <1.6.0","twig\/twig":"^3.4"}},{"version":"1.1.7","require":{"atrocore\/core":">1.4.146 <1.6.0","twig\/twig":"^3.4"}},{"version":"1.1.6","require":{"atrocore\/core":">1.4.146 <1.6.0","twig\/twig":"^3.4"}},{"version":"1.1.5","require":{"atrocore\/core":">1.4.146 <1.6.0","twig\/twig":"^3.4"}},{"version":"1.1.4","require":{"atrocore\/core":">1.4.146 <1.6.0","twig\/twig":"^3.4"}},{"version":"1.1.3","require":{"atrocore\/core":">1.4.146 <1.6.0","twig\/twig":"^3.4"}},{"version":"1.1.2","require":{"atrocore\/core":">1.4.146 <1.6.0","twig\/twig":"^3.4"}},{"version":"1.1.1","require":{"atrocore\/core":">1.4.146 <1.6.0","twig\/twig":"^3.4"}},{"version":"1.1.0","require":{"atrocore\/core":">1.4.146 <1.6.0","twig\/twig":"^3.4"}}]', true),
            'tags'           => []
        ];

        // filter by status
        if (!empty($params['whereClause'][0]['status!='])) {
            foreach ($params['whereClause'][0]['status!='] as $status) {
                foreach ($items as $code => $row) {
                    if ($row['status'] === $status) {
                        unset($items[$code]);
                    }
                }
            }
        }

        return $items;
    }
}
