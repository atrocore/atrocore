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

namespace Atro\TwigFunction;

use Atro\Core\Twig\AbstractTwigFunction;
use Espo\Core\ORM\Entity;

class EditableEntity extends AbstractTwigFunction
{
    public function __construct()
    {
        $this->addDependency('acl');
        $this->addDependency('metadata');
    }

    public function run(mixed $value, array $fields = []): ?string
    {
        if (!$value instanceof Entity) {
            return null;
        }

        if (!$this->getInjection('acl')->check($value->getEntityType(), 'edit')) {
            return null;
        }

        $filteredFields = [];
        foreach ($fields as $field) {
            if ($this->hasField($value, $field)) {
                $filteredFields[] = $field;
            }
        }

        if (!empty($fields) && empty($filteredFields)) {
            return null;
        }

        $filteredFields = array_map(function ($field) use ($value) {
            if (!empty($value->fields[$field]['measureId'])) {
                return 'unit' . ucfirst($field);
            }

            return $field;
        }, $filteredFields);

        $result = "data-editor-type={$value->getEntityType()} data-editor-id={$value->id}";
        if (!empty($filteredFields)) {
            $result .= ' data-editor-fields=' . implode(',', $filteredFields);
        }

        return $result;
    }

    private function hasField(Entity $entity, string $field): bool
    {
        if (array_key_exists($field, $entity->fields)) {
            return true;
        } else if (($defs = $entity->get('attributesDefs')) && is_array($defs)) {
            return array_key_exists($field, $defs);
        }

        return false;
    }
}