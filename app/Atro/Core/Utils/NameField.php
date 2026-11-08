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

namespace Atro\Core\Utils;

use Atro\Core\Container;
use Espo\ORM\Entity;

/**
 * The field naming the records of an entity (`scopes.{Entity}.nameField`, `name` by default) and its value in the
 * language of the user.
 */
class NameField
{
    public function __construct(
        private readonly Metadata $metadata,
        private readonly Container $container
    ) {
    }

    public function getField(string $entityName): string
    {
        $nameField = $this->metadata->get(['scopes', $entityName, 'nameField']);
        if (empty($nameField) || empty($this->metadata->get(['entityDefs', $entityName, 'fields', $nameField]))) {
            $nameField = 'name';
        }

        return $nameField;
    }

    /**
     * The field holding the name in the language of the user, or null when the name is not translated.
     */
    public function getLocalizedField(string $entityName): ?string
    {
        $nameField = $this->getField($entityName);
        $name = Language::getLocalizedFieldName($this->container, $entityName, $nameField);

        return $name !== $nameField ? $name : null;
    }

    /**
     * The name of the record in the language of the user, the name in the main language when it is not translated.
     *
     * @param Entity|array $record
     */
    public function getLocalizedValue($record, string $entityName): string
    {
        $nameField = $this->getField($entityName);
        $localizedName = $this->getLocalizedField($entityName);

        if (!empty($localizedName)) {
            $value = is_array($record) ? $record[$localizedName] : $record->get($localizedName);
            if (!empty($value)) {
                return (string)$value;
            }
        }

        return (string)(is_array($record) ? $record[$nameField] : $record->get($nameField));
    }
}
