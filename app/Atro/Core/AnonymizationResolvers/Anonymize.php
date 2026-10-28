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

namespace Atro\Core\AnonymizationResolvers;

use Atro\Core\Exceptions\Error;
use Espo\ORM\Entity;

class Anonymize extends AbstractAnonymizationResolver
{
    public const VALUE = 'anonymized';
    public const OPTION = '_anonymized';
    public const URL = 'https://anonymized.invalid/';
    public const EMAIL_DOMAIN = 'anonymized.invalid';

    public const SUPPORTED_TYPES = [
        'varchar',
        'text',
        'markdown',
        'wysiwyg',
        'url',
        'email',
        'date',
        'datetime',
        'datetimeOptional',
        'int',
        'float',
        'rangeInt',
        'rangeFloat',
        'enum',
        'multiEnum',
        'array',
    ];

    public function supports(Entity $entity, string $field): bool
    {
        return in_array($this->getFieldDefs($entity, $field)['type'] ?? null, self::SUPPORTED_TYPES);
    }

    public function resolve(Entity $entity, string $field, array &$processed = []): bool
    {
        $type = $this->getFieldDefs($entity, $field)['type'] ?? null;

        // the range is unique as a whole, so both values are anonymized together
        if (in_array($type, ['rangeInt', 'rangeFloat'])) {
            $this->anonymizeRange($entity, $field);
            return true;
        }

        $this->anonymizeField($entity, $field);

        return true;
    }

    protected function anonymizeField(Entity $entity, string $field): void
    {
        $defs     = $this->getFieldDefs($entity, $field);
        $type     = $defs['type'] ?? null;
        $isUnique = !empty($defs['unique']);

        $isEmpty = true;
        foreach ($this->getActualFields($entity, $field) as $attribute) {
            if (!in_array($entity->get($attribute), [null, '', []], true)) {
                $isEmpty = false;
            }
        }

        // there is nothing to anonymize
        if ($isEmpty) {
            return;
        }

        switch ($type) {
            case 'varchar':
            case 'text':
            case 'markdown':
            case 'wysiwyg':
                $value = $isUnique ? $this->generateUniqueText($entity, $field, $defs) : self::VALUE;
                $entity->set($field, $this->cutToMaxLength($value, $defs));
                break;
            case 'url':
                $value = $isUnique ? $this->generateUniqueText($entity, $field, $defs, self::URL) : self::URL;
                $entity->set($field, $this->cutToMaxLength($value, $defs));
                break;
            case 'email':
                $entity->set($field, $this->generateUniqueText($entity, $field, $defs, 'anon-', '@' . self::EMAIL_DOMAIN));
                break;
            case 'date':
            case 'datetime':
            case 'datetimeOptional':
                // unique dates cannot be anonymized without collisions
                if ($isUnique) {
                    $this->nullify($entity, $field);
                    break;
                }
                foreach ($this->getActualFields($entity, $field) as $attribute) {
                    $entity->set($attribute, $this->truncateToYear($entity->get($attribute)));
                }
                break;
            case 'int':
            case 'float':
                if ($isUnique) {
                    $entity->set($field, $this->generateUniqueNumber($entity, [$field], $defs, $type === 'float'));
                } else {
                    $entity->set($field, $this->getZero($defs, $type === 'float'));
                }
                break;
            case 'enum':
                $this->createOption($entity, $field);
                $entity->set($field, self::OPTION);
                break;
            case 'multiEnum':
                $this->createOption($entity, $field);
                $entity->set($field, [self::OPTION]);
                break;
            case 'array':
                $entity->set($field, [self::OPTION]);
                break;
            default:
                $this->nullify($entity, $field);
        }
    }

    protected function anonymizeRange(Entity $entity, string $field): void
    {
        $from = $field . 'From';
        $to   = $field . 'To';

        if ($entity->get($from) === null && $entity->get($to) === null) {
            return;
        }

        $isFloat = ($this->getFieldDefs($entity, $field)['type'] ?? null) === 'rangeFloat';

        if (!empty($this->getFieldDefs($entity, $field)['unique'])) {
            // both values are the same, so "from" is never greater than "to"
            $value = $this->generateUniqueNumber($entity, [$from, $to], $this->getFieldDefs($entity, $from), $isFloat);
            $entity->set($from, $value);
            $entity->set($to, $value);
            return;
        }

        $entity->set($from, $this->getZero($this->getFieldDefs($entity, $from), $isFloat));
        $entity->set($to, $this->getZero($this->getFieldDefs($entity, $to), $isFloat));
    }

    protected function generateUniqueText(Entity $entity, string $field, array $defs, string $prefix = self::VALUE . '-', string $suffix = ''): string
    {
        for ($i = 0; $i < 100; $i++) {
            $value = $this->cutToMaxLength($prefix . $this->generateHash(), $defs, $suffix) . $suffix;
            if (!$this->isValueUsed($entity, [$field => $value])) {
                return $value;
            }
        }

        throw new Error("Unique value for '$field' cannot be generated.");
    }

    protected function generateHash(): string
    {
        return bin2hex(random_bytes(16));
    }

    protected function isValueUsed(Entity $entity, array $where): bool
    {
        return $this->getEntityManager()
                ->getRepository($entity->getEntityName())
                ->where(array_merge($where, ['id!=' => $entity->id]))
                ->count() > 0;
    }

    protected function cutToMaxLength(string $value, array $defs, string $suffix = ''): string
    {
        $maxLength = $defs['maxLength'] ?? null;
        if (empty($maxLength) && ($defs['type'] ?? null) === 'varchar') {
            $maxLength = 255;
        }

        if (!empty($maxLength)) {
            $value = mb_substr($value, 0, max(0, (int)$maxLength - mb_strlen($suffix)));
        }

        return $value;
    }

    protected function truncateToYear(?string $value): ?string
    {
        if (empty($value)) {
            return $value;
        }

        $year = substr($value, 0, 4);

        // date has 10 characters, datetime is longer
        return strlen($value) > 10 ? "$year-01-01 00:00:00" : "$year-01-01";
    }

    protected function getZero(array $defs, bool $isFloat): int|float
    {
        $value = 0;

        // the value must stay valid
        if (isset($defs['min']) && $defs['min'] > $value) {
            $value = $defs['min'];
        }
        if (isset($defs['max']) && $defs['max'] < $value) {
            $value = $defs['max'];
        }

        return $isFloat ? (float)$value : (int)$value;
    }

    /**
     * Generates a random unique number that starts with "9" and has the maximum possible amount of digits
     */
    protected function generateUniqueNumber(Entity $entity, array $attributes, array $defs, bool $isFloat): int|float
    {
        if (isset($defs['max'])) {
            $max = (int)floor((float)$defs['max']);
        } elseif ($isFloat) {
            // float keeps 15 significant digits without losing precision
            $max = 999999999999999;
        } elseif (($defs['dbType'] ?? null) === 'bigint') {
            $max = PHP_INT_MAX;
        } else {
            $max = 2147483647;
        }

        $digits = strlen((string)$max);
        $min    = 9 * 10 ** ($digits - 1);
        if ($min > $max) {
            $digits--;
            $min = 9 * 10 ** ($digits - 1);
        }

        if ($digits < 1) {
            throw new Error("Unique number for '{$attributes[0]}' cannot be generated.");
        }

        $max = min($max, (int)str_repeat('9', $digits));

        for ($i = 0; $i < 100; $i++) {
            $value = random_int($min, $max);

            $where = [];
            foreach ($attributes as $attribute) {
                $where[$attribute] = $value;
            }

            if (!$this->isValueUsed($entity, $where)) {
                return $isFloat ? (float)$value : $value;
            }
        }

        throw new Error("Unique number for '{$attributes[0]}' cannot be generated.");
    }

    /**
     * Adds "_anonymized" option to the enum field, so the anonymized value stays valid
     */
    protected function createOption(Entity $entity, string $field): void
    {
        $options = $this->getFieldDefs($entity, $field)['options'] ?? [];
        if (in_array(self::OPTION, $options)) {
            return;
        }

        $fieldEntity = $this->getEntityManager()->getRepository('EntityField')->get($entity->getEntityType() . '_' . $field);
        if (empty($fieldEntity)) {
            throw new Error("Field '$field' is not found.");
        }

        $fieldOptions = $fieldEntity->get('options') ?? [];
        if (!in_array(self::OPTION, $fieldOptions)) {
            $fieldEntity->set('options', array_merge($fieldOptions, [self::OPTION]));
            $this->getEntityManager()->saveEntity($fieldEntity);
        }

        // the record was loaded with the old options, they are used for the validation
        $entity->entityDefs['fields'][$field]['options'] = array_merge($options, [self::OPTION]);
    }
}
