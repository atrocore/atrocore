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

        $fieldsMetadata = $this->getInjection('metadata')->get(['entityDefs', $value->getEntityType(), 'fields']);
        $filteredFields = array_filter($fields, fn($field) => array_key_exists($field, $fieldsMetadata));

        if (!empty($fields) && empty($filteredFields)) {
            return null;
        }

        $filteredFields = array_map(function ($field) use ($fieldsMetadata) {
            if (!empty($fieldsMetadata[$field]['measureId'])) {
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
}