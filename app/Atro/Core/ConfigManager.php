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

namespace Atro\Core;

use Espo\Core\Injectable;

class ConfigManager extends Injectable
{
    protected $config;

    public function __construct()
    {
        $this->addDependency('entityManager');
    }

    public static function getType(string $type): string
    {
        return strtolower(str_replace(" ", '-', $type));
    }

    /**
     * @param array $path
     * @param array $config
     *
     * @return array|mixed|null|string
     */
    public function get(array $path, array $config = [])
    {
        if (!$config) {
            $config = $this->getConfig();
        }

        foreach ($path as $pathItem) {
            if (isset($config[$pathItem])) {
                $config = $config[$pathItem];
            } else {
                return null;
            }
        }

        return $config;
    }

    /**
     * @param array $path
     *
     * @return array|mixed|string|null
     */
    public function getByType(array $path)
    {
        $config = $this->getConfig();

        if (!isset($config['type']['custom'][$path[0]])) {
            return $config['default'];
        }

        return $this->get($path, $config['type']['custom']);
    }

    /**
     * @return mixed
     */
    public function getConfig()
    {
        if (!$this->config) {
            $result = [
                'type'             => [
                    'custom'  => [
                        'file' => [
                            'validations' => [],
                            'renditions'  => [],
                        ]
                    ],
                    'default' => [
                        'validations' => [],
                        'renditions'  => [],
                    ]
                ],
                'attributeMapping' => [
                    'size'        => [
                        'field' => 'size',
                    ],
                    'orientation' => [
                        'field' => 'orientation',
                    ],
                    'width'       => [
                        'field' => 'width',
                    ],
                    'height'      => [
                        'field' => 'height',
                    ],
                    'color-depth' => [
                        'field' => 'colorDepth',
                    ],
                    'color-space' => [
                        'field' => 'colorSpace',
                    ],
                ]
            ];

            $types = $this
                ->getInjection('entityManager')
                ->getRepository('AssetType')
                ->find();

            if ($types->count() > 0) {
                foreach ($types as $type) {
                    $name = strtolower(str_replace(" ", "-", $type->get('name')));
                    $result['type']['custom'][$name] = [
                        'validations' => $type->getValidations(),
                        'renditions'  => $type->getRenditions(),
                    ];
                }
            }

            $this->config = $result;
        }

        return $this->config;
    }
}