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

namespace Atro\Core\Utils\Database\Schema;

use Atro\Core\Container;
use Doctrine\DBAL\Schema\Schema;
use Espo\Core\Utils\Metadata\OrmMetadata;

/**
 * @todo 1. delete app/Espo/Core/Utils/Database/Schema/tables/
 */
class Converter
{
    protected OrmMetadata $ormMetadata;

    public function __construct(Container $container)
    {
        $this->ormMetadata = $container->get('ormMetadata');
    }

    public function createSchema(): Schema
    {
        $ormMetadata = array_merge($this->ormMetadata->getData(), $this->getSystemOrmMetadata());

        echo '<pre>';
        print_r($ormMetadata);
        die();
    }

    protected function getSystemOrmMetadata(): array
    {
        return [
            'unset'        => array(
                'Preferences',
                'Settings',
            ),
            'unsetIgnore'  => [
                ['Preferences', 'fields', 'id'],
                ['Preferences', 'fields', 'data']
            ],
            'Autofollow'   => [
                'fields' => [
                    'id'         => [
                        'type'          => 'id',
                        'dbType'        => 'int',
                        'len'           => '11',
                        'autoincrement' => true,
                        'unique'        => true,
                    ],
                    'entityType' => [
                        'type'  => 'varchar',
                        'len'   => '100',
                        'index' => 'entityType',
                    ],
                    'userId'     => [
                        'type'  => 'varchar',
                        'len'   => '24',
                        'index' => true,
                    ]
                ]
            ],
            'Preferences'  => [
                'fields' => [
                    'id'   => [
                        'dbType' => 'varchar',
                        'len'    => 24,
                        'type'   => 'id'
                    ],
                    'data' => [
                        'type' => 'text'
                    ]
                ]
            ],
            'Subscription' => [
                'fields' => [
                    'id'         => [
                        'type'          => 'id',
                        'dbType'        => 'int',
                        'len'           => '11',
                        'autoincrement' => true,
                        'unique'        => true,
                    ],
                    'entityId'   => [
                        'type'  => 'varchar',
                        'len'   => '24',
                        'index' => 'entity',
                    ],
                    'entityType' => [
                        'type'  => 'varchar',
                        'len'   => '100',
                        'index' => 'entity',
                    ],
                    'userId'     => [
                        'type'  => 'varchar',
                        'len'   => '24',
                        'index' => true,
                    ],
                ],
            ],
        ];
    }
}