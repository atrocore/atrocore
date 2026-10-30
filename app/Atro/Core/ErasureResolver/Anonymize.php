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

namespace Atro\Core\ErasureResolver;

use Atro\Core\Exceptions\Error;
use Espo\ORM\Entity;

class Anonymize extends AbstractErasureResolver
{
    public const DEFAULT_VALUE = 'anonymized';
    public const DEFAULT_OPTION = '_anonymized';
    public const DEFAULT_URL = 'https://anonymized.invalid/';
    public const DEFAULT_EMAIL_DOMAIN = 'anonymized.invalid';

    public const SUPPORTED_TYPES = [
        'varchar',
        'text',
        'markdown',
        'wysiwyg',
        'url',
        'email',
        'date',
        'datetime',
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

        if ($isEmpty) {
            return;
        }

        switch ($type) {
            case 'varchar':
            case 'text':
            case 'markdown':
            case 'wysiwyg':
                $value = $isUnique ? $this->generateUniqueText($entity, $field, $defs) : self::DEFAULT_VALUE;
                $entity->set($field, $this->cutToMaxLength($value, $defs));
                break;
            case 'url':
                $value = $isUnique ? $this->generateUniqueText($entity, $field, $defs, self::DEFAULT_URL) : self::DEFAULT_URL;
                $entity->set($field, $this->cutToMaxLength($value, $defs));
                break;
            case 'email':
                // the email always gets a random hash, not derived from the previous value
                $suffix = '@' . self::DEFAULT_EMAIL_DOMAIN;
                $value = $isUnique
                    ? $this->generateUniqueText($entity, $field, $defs, 'anon-', $suffix)
                    : $this->cutToMaxLength('anon-' . $this->generateHash(), $defs, $suffix) . $suffix;
                $entity->set($field, $value);
                break;
            case 'date':
            case 'datetime':
                if ($isUnique) {
                    $this->nullify($entity, $field);
                    break;
                }
                $entity->set($field, $this->prepareDate($entity->get($field))->format($type === 'datetime' ? 'Y-m-d H:i:s' : 'Y-m-d'));
                break;
            case 'int':
            case 'float':
                if ($isUnique) {
                    $entity->set($field, $this->generateUniqueNumber($entity, [$field], $defs, $type === 'float'));
                } else {
                    $entity->set($field, $this->getZero($defs));
                }
                break;
            case 'rangeInt':
            case 'rangeFloat':
                $from = $field . 'From';
                $to   = $field . 'To';

                if ($isUnique) {
                    // the same value must fit the limits of both parts
                    $rangeDefs = $this->getFieldDefs($entity, $from);
                    $toDefs    = $this->getFieldDefs($entity, $to);
                    if (isset($toDefs['min'])) {
                        $rangeDefs['min'] = max($rangeDefs['min'] ?? $toDefs['min'], $toDefs['min']);
                    }
                    if (isset($toDefs['max'])) {
                        $rangeDefs['max'] = min($rangeDefs['max'] ?? $toDefs['max'], $toDefs['max']);
                    }

                    // the range is unique as a whole, the same value in both parts keeps "from" not greater than "to"
                    $value = $this->generateUniqueNumber($entity, [$from, $to], $rangeDefs, $type === 'rangeFloat');
                    $entity->set($from, $value);
                    $entity->set($to, $value);
                } else {
                    $entity->set($from, $this->getZero($this->getFieldDefs($entity, $from)));
                    $entity->set($to, $this->getZero($this->getFieldDefs($entity, $to)));
                }
                break;
            case 'enum':
                $this->createOption($entity, $field);
                $entity->set($field, self::DEFAULT_OPTION);
                break;
            case 'multiEnum':
                $this->createOption($entity, $field);
                $entity->set($field, [self::DEFAULT_OPTION]);
                break;
            case 'array':
                $entity->set($field, [self::DEFAULT_OPTION]);
                break;
            default:
                $this->nullify($entity, $field);
        }
    }

    protected function generateUniqueText(Entity $entity, string $field, array $defs, string $prefix = self::DEFAULT_VALUE . '-', string $suffix = ''): string
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

    protected function prepareDate(?string $value): \DateTime
    {
        $dateTime = new \DateTime(empty($value) ? 'now' : $value);

        return $dateTime->setDate((int)$dateTime->format('Y'), 1, 1)->setTime(0, 0);
    }

    protected function getZero(array $defs): int|float
    {
        $value = 0;

        // the value must stay valid
        if (isset($defs['min']) && $defs['min'] > $value) {
            $value = $defs['min'];
        }
        if (isset($defs['max']) && $defs['max'] < $value) {
            $value = $defs['max'];
        }

        return $value;
    }

    /**
     * Generates a random unique number that starts with "9" and has the maximum possible amount of digits,
     * e.g. 900000000 - 999999999 for the int field
     */
    protected function generateUniqueNumber(Entity $entity, array $attributes, array $defs, bool $isFloat): int|float
    {
        $digits = $this->getMaxDigits($defs, $isFloat);
        if ($digits < 1) {
            throw new Error("Unique number for '{$attributes[0]}' cannot be generated.");
        }

        $min = 9 * 10 ** ($digits - 1);
        $max = (int)str_repeat('9', $digits);

        // the value must stay valid
        if (isset($defs['min'])) {
            $min = max($min, (int)ceil((float)$defs['min']));
        }
        if (isset($defs['max'])) {
            $max = min($max, (int)floor((float)$defs['max']));
        }

        if ($min > $max) {
            throw new Error("Unique number for '{$attributes[0]}' cannot be generated.");
        }

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
     * Returns the maximum amount of digits of the number that starts with "9" and fits the field
     */
    protected function getMaxDigits(array $defs, bool $isFloat): int
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

        if ($max < 9) {
            return 0;
        }

        $digits = strlen((string)$max);

        // e.g. the int max is 2147483647, so 9000000000 does not fit and only 9 digits are possible
        if (9 * 10 ** ($digits - 1) > $max) {
            $digits--;
        }

        return $digits;
    }

    /**
     * Adds "_anonymized" option to the enum field, so the anonymized value stays valid
     */
    protected function createOption(Entity $entity, string $field): void
    {
        $options = $this->getFieldDefs($entity, $field)['options'] ?? [];
        if (in_array(self::DEFAULT_OPTION, $options)) {
            return;
        }

        $fieldEntity = $this->getEntityManager()->getRepository('EntityField')->get($entity->getEntityType() . '_' . $field);
        if (empty($fieldEntity)) {
            throw new Error("Field '$field' is not found.");
        }

        $fieldOptions = $fieldEntity->get('options') ?? [];
        if (!in_array(self::DEFAULT_OPTION, $fieldOptions)) {
            $fieldEntity->set('options', array_merge($fieldOptions, [self::DEFAULT_OPTION]));
            $this->getEntityManager()->saveEntity($fieldEntity);
        }

        // the record was loaded with the old options, they are used for the validation
        $entity->entityDefs['fields'][$field]['options'] = array_merge($options, [self::DEFAULT_OPTION]);
    }
}
