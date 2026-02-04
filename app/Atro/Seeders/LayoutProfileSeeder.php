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

use Atro\Core\Utils\IdGenerator;
use Doctrine\DBAL\ParameterType;

class LayoutProfileSeeder extends AbstractSeeder
{
    public function run(): void
    {
        $defaultId = $this->getIdGenerator()->toUuid('default');
        $menus = $this->getDefaultMenu();
        $favList = $this->getFavorites();
        $defaultDashboards = $this->getDefaultDashboards();

        try {
            // remove default layout profile if exists
            $this->getConnection()->createQueryBuilder()
                ->delete('layout_profile')
                ->where('id = :id')
                ->setParameter('id', $defaultId)
                ->executeQuery();

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
                    'dashlets_options' => ':dashletsOptions',
                    'favorites_list'   => ':favorites_list'
                ])->setParameters([
                    'id'              => $defaultId,
                    'name'            => 'Standard',
                    'navigation'      => json_encode($menus),
                    'favorites_list'  => json_encode($favList),
                    'dashboardLayout' => json_encode($defaultDashboards['layout']),
                    'dashletsOptions'  => json_encode($defaultDashboards['options']),
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
        $menus = ['Dashboard', 'Product', 'File', 'Attribute', 'AttributePanel', 'AttributeGroup', 'Classification'];

        if (class_exists('\Pim\Module')) {
            $menus = array_merge($menus, [
                'Association',
                'Brand',
                'Category',
                'Catalog',
                'Channel'
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
        $favList = ['Product', 'File', 'Classification'];

        if (class_exists('\Import\Module')) {
            $favList[] = 'ImportFeed';
        }

        if (class_exists('\Export\Module')) {
            $favList[] = 'ExportFeed';
        }

        return $favList;
    }

    private function getDefaultDashboards(): array
    {
        $gettingStartedLayout = [
            'name'   => 'Getting started',
            'layout' => [
                [
                    'id'     => 'd681015',
                    'name'   => 'FirstSteps',
                    'x'      => 0,
                    'y'      => 0,
                    'width'  => 2,
                    'height' => 4
                ],
                [
                    'id'     => 'd685698',
                    'name'   => 'Entities',
                    'x'      => 2,
                    'y'      => 0,
                    'width'  => 2,
                    'height' => 4
                ]
            ]
        ];

        $data = [
            'layout'  => [
                [
                    'name'   => 'Insights',
                    'layout' => [
                        [
                            'id'     => 'd678833',
                            'name'   => 'Records',
                            'x'      => 0,
                            'y'      => 0,
                            'width'  => 2,
                            'height' => 4
                        ],
                        [
                            'id'     => 'd811129',
                            'name'   => 'Stream',
                            'x'      => 2,
                            'y'      => 0,
                            'width'  => 2,
                            'height' => 4
                        ],
                        [
                            'id'     => 'd556889',
                            'name'   => 'DataSyncErrorsExport',
                            'x'      => 0,
                            'y'      => 4,
                            'width'  => 2,
                            'height' => 2
                        ],
                        [
                            'id'     => 'd403401',
                            'name'   => 'DataSyncErrorsImport',
                            'x'      => 2,
                            'y'      => 4,
                            'width'  => 2,
                            'height' => 2
                        ]
                    ]
                ]
            ],
            'options' => [
                'd678833' => [
                    'autorefreshInterval' => 0.5,
                    'displayRecords'      => 20,
                    'entityType'          => 'Account',
                    'sortBy'              => 'createdAt',
                    'sortDirection'       => 'desc',
                    'title'               => 'Customer',
                    'entityFilter'        => [
                        'where'      => [
                            [
                                'condition' => 'AND',
                                'valid'     => true,
                                'rules'     => [
                                    [
                                        'id'       => 'role',
                                        'field'    => 'role',
                                        'operator' => 'in',
                                        'type'     => 'string',
                                        'value'    => ['customer']
                                    ]
                                ]
                            ]
                        ],
                        'whereScope' => 'Account'
                    ]
                ],
                'd685698' => [
                    'entityListType' => 'all'
                ]
            ]
        ];

        if (class_exists('\Pim\Module')) {
            $data = \Pim\Module::DASHLETS_DATA;
        }

        array_unshift($data['layout'], $gettingStartedLayout);

        return $data;
    }
}