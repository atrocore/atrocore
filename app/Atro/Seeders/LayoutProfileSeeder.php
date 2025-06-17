<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Seeders;

use Doctrine\DBAL\ParameterType;

class LayoutProfileSeeder extends AbstractSeeder
{
    public function run(): void
    {
        $defaultId = 'default';
        $menus = $this->getDefaultMenu();
        $favList = $this->getFavorites();

        try {
            // create default profile
            $this->getConnection()->createQueryBuilder()
                ->insert('layout_profile')
                ->values([
                    'id'               => ':id',
                    'name'             => ':name',
                    'is_active'        => ':true',
                    'is_default'       => ':true',
                    'navigation'       => ':navigation',
                    'dashboard_layout' => ':dashboardLayout',
                    'favorites_list'   => ':favorites_list'
                ])->setParameters([
                    'id'              => $defaultId,
                    'name'            => 'Standard',
                    'navigation'      => json_encode($menus),
                    'favorites_list'  => json_encode($favList),
                    'dashboardLayout' => json_encode([
                        [
                            'name'   => 'My AtroPIM',
                            'layout' => []
                        ]]),
                ])
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->executeStatement();

            // update layout profile for all users
            $this->getConnection()->createQueryBuilder()
                ->update($this->getConnection()->quoteIdentifier('user'))
                ->set('layout_profile_id', ':id')
                ->where('id is not null')
                ->setParameter('id', $defaultId)
                ->executeStatement();
        } catch (\Throwable $e) {
        }
    }

    private function getDefaultMenu(): array
    {
        $menus = ['Product', 'File'];

        if (class_exists('\Pim\Module')) {
            $menus = array_merge($menus, [
                'Association',
                'Attribute',
                'AttributeGroup',
                'Brand',
                'Category',
                'Catalog',
                'Channel',
                'Classification'
            ]);
        }

        if (class_exists('\Export\Module')) {
            $menus[] = 'ExportFeed';
        }

        if (class_exists('\Import\Module')) {
            $menus[] = 'ImportFeed';
        }

        return $menus;
    }

    private function getFavorites(): array
    {
        $favList = ['Product', 'File'];

        if (class_exists('\Pim\Module')) {
            $favList = array_merge(['Classification'], $favList);
        }

        if (class_exists('\Import\Module')) {
            $favList[] = 'ImportFeed';
        }

        if (class_exists('\Export\Module')) {
            $favList[] = 'ExportFeed';
        }

        return $favList;
    }
}