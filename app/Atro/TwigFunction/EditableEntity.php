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
    }

    public function run(mixed $entity, array $fields = []): ?string
    {
        if (!$entity instanceof Entity) {
            return null;
        }

        if (!$this->getInjection('acl')->check($entity->getEntityType(), 'edit')) {
            return null;
        }

        $filteredFields = [];
        foreach ($fields as $field) {
            if ($this->hasField($entity, $field)) {
                $filteredFields[] = $field;
            }
        }

        if (!empty($fields) && empty($filteredFields)) {
            return null;
        }

        $filteredFields = array_map(function ($field) use ($entity) {
            if (!empty($entity->fields[$field]['measureId'])) {
                return 'unit' . ucfirst($field);
            }

            return $field;
        }, $filteredFields);

        $result = "data-editor-type={$entity->getEntityType()} data-editor-id={$entity->id}";
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