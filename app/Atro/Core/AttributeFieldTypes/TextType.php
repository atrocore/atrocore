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

namespace Atro\Core\AttributeFieldTypes;

use Atro\Core\Container;
use Atro\Core\Utils\Config;
use Atro\Core\Utils\Util;
use Espo\ORM\IEntity;

class TextType implements AttributeFieldTypeInterface
{
    protected string $type = 'text';
    protected string $column = 'text_value';
    protected Config $config;

    public function __construct(Container $container)
    {
        $this->config = $container->get('config');
    }

    public function convert(IEntity $entity, string $id, string $name, array $row, array &$attributesDefs): void
    {
        $attributeData = @json_decode($row['data'], true)['field'] ?? null;

        $entity->fields[$name] = [
            'type'             => $this->type,
            'name'             => $name,
            'attributeValueId' => $id,
            'column'           => $this->column,
            'required'         => !empty($row['is_required'])
        ];

        $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);

        $entity->entityDefs['fields'][$name] = [
            'attributeValueId' => $id,
            'type'             => $this->type,
            'required'         => !empty($row['is_required']),
            'notNull'          => !empty($row['not_null']),
            'label'            => $row['name'],
            'tooltip'          => !empty($row['tooltip']),
            'tooltipText'      => $row['tooltip']
        ];

        if (!empty($attributeData['maxLength'])) {
            $entity->entityDefs['fields'][$name]['maxLength'] = $attributeData['maxLength'];
        }

        if (!empty($attributeData['countBytesInsteadOfCharacters'])) {
            $entity->entityDefs['fields'][$name]['countBytesInsteadOfCharacters'] = $attributeData['countBytesInsteadOfCharacters'];
        }

        $attributesDefs[$name] = $entity->entityDefs['fields'][$name];

        $languages = [];
        if (!empty($this->config->get('isMultilangActive'))) {
            foreach ($this->config->get('inputLanguageList', []) as $code) {
                $languages[$code] = $code;
                foreach ($this->config->get('referenceData.Language', []) as $v) {
                    if ($code === $v['code']) {
                        $languages[$code] = $v['name'];
                        break;
                    }
                }
            }
        }

        if (!empty($row['is_multilang'])) {
            foreach ($languages as $language => $languageName) {
                $lName = $name . ucfirst(Util::toCamelCase(strtolower($language)));
                $entity->fields[$lName] = array_merge($entity->entityDefs['fields'][$name], [
                    'name'   => $lName,
                    'column' => $this->column . "_" . strtolower($language)
                ]);
                $entity->set($lName, $row[$entity->fields[$lName]['column']] ?? null);

                $entity->entityDefs['fields'][$lName] = array_merge($entity->entityDefs['fields'][$name], [
                    'name'        => $lName,
                    'label'       => $row['name'] . ' / ' . $languageName,
                    'tooltip'     => !empty($row['tooltip_' . strtolower($language)]),
                    'tooltipText' => $row['tooltip_' . strtolower($language)]
                ]);

                $attributesDefs[$lName] = $entity->entityDefs['fields'][$lName];
            }
        }
    }
}
