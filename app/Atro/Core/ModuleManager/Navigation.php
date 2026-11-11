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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Adds the items of a module to the navigation of the default layout profile and removes them. It uses the
 * database connection only, so an AfterDelete step can use it too: `new Navigation($this->connection)`.
 *
 * An item is the name of an entity or a group: `['id' => 'group', 'name' => 'Group', 'items' => ['Entity']]`.
 */
class Navigation
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function addItems(array $items): void
    {
        $profile = $this->getDefaultProfile();
        if ($profile === null) {
            return;
        }

        $navigation = $profile['navigation'];

        $existing = [];
        foreach ($navigation as $item) {
            if (is_array($item) && !empty($item['items'])) {
                foreach ($item['items'] as $subItem) {
                    $existing[] = $subItem;
                }
            } else {
                $existing[] = $item;
            }
        }

        foreach ($items as $item) {
            if (is_array($item) && !empty($item['id']) && !empty($item['items'])) {
                $navigation = $this->addGroup($navigation, $item, $existing);
            } elseif (!in_array($item, $existing, true)) {
                $navigation[] = $item;
            }
        }

        $this->saveNavigation($profile['id'], $navigation);
    }

    public function removeItems(array $items): void
    {
        $profile = $this->getDefaultProfile();
        if ($profile === null) {
            return;
        }

        $navigation = $profile['navigation'];

        foreach ($items as $item) {
            if (!is_string($item)) {
                continue;
            }

            if (in_array($item, $navigation, true)) {
                $navigation = $this->withoutItem($navigation, $item);
                continue;
            }

            foreach ($navigation as $key => $navigationItem) {
                if (is_array($navigationItem) && !empty($navigationItem['items']) && in_array($item, $navigationItem['items'], true)) {
                    $navigation[$key]['items'] = $this->withoutItem($navigationItem['items'], $item);
                    if (empty($navigation[$key]['items'])) {
                        unset($navigation[$key]);
                    }
                }
            }
        }

        $this->saveNavigation($profile['id'], array_values($navigation));
    }

    private function addGroup(array $navigation, array $group, array $existing): array
    {
        $subItems = [];
        foreach ($group['items'] as $subItem) {
            if (!in_array($subItem, $existing, true)) {
                $subItems[] = $subItem;
            }
        }

        if (empty($subItems)) {
            return $navigation;
        }

        foreach ($navigation as $key => $navigationItem) {
            if (is_array($navigationItem) && ($navigationItem['id'] ?? null) === $group['id']) {
                $navigation[$key]['items'] = array_merge($navigationItem['items'] ?? [], $subItems);

                return $navigation;
            }
        }

        $group['items'] = $subItems;
        $navigation[] = $group;

        return $navigation;
    }

    private function withoutItem(array $list, string $item): array
    {
        $result = [];
        foreach ($list as $value) {
            if ($value !== $item) {
                $result[] = $value;
            }
        }

        return $result;
    }

    private function getDefaultProfile(): ?array
    {
        $profile = $this->connection->createQueryBuilder()
            ->select('id, navigation')
            ->from('layout_profile')
            ->where('is_default = :true')
            ->andWhere('deleted = :false')
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAssociative();

        if (empty($profile)) {
            return null;
        }

        return [
            'id'         => $profile['id'],
            'navigation' => @json_decode((string)$profile['navigation'], true) ?? [],
        ];
    }

    private function saveNavigation(string $profileId, array $navigation): void
    {
        $this->connection->createQueryBuilder()
            ->update('layout_profile')
            ->set('navigation', ':navigation')
            ->where('id = :id')
            ->setParameter('navigation', json_encode($navigation))
            ->setParameter('id', $profileId)
            ->executeStatement();
    }
}
