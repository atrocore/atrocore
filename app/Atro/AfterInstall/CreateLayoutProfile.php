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

namespace Atro\AfterInstall;

use Atro\Core\ModuleManager\AfterInstallInterface;
use Espo\ORM\EntityManager;

/**
 * Creates the default layout profile with the navigation, the favorites and the dashboards, and sets it to
 * every user.
 */
class CreateLayoutProfile implements AfterInstallInterface
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    public static function getSortOrder(): int
    {
        return 70;
    }

    public function run(): void
    {
        $defaultDashboards = $this->getDefaultDashboards();

        $profile = $this->entityManager->getRepository('LayoutProfile')->get();
        $profile->set([
            'id'              => '019c0a6a-6ecb-70d3-8427-cbc546cad873',
            'name'            => 'Standard',
            'isActive'        => true,
            'isDefault'       => true,
            'navigation'      => $this->getDefaultMenu(),
            'favoritesList'   => $this->getFavorites(),
            'dashboardLayout' => $defaultDashboards['layout'],
            'dashletsOptions' => $defaultDashboards['options'],
        ]);
        $this->entityManager->saveEntity($profile);

        foreach ($this->entityManager->getRepository('User')->find() as $user) {
            $user->set('layoutProfileId', $profile->get('id'));
            $this->entityManager->saveEntity($user);
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