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

namespace Atro\Core\Utils\Condition;

use DateInterval;
use Atro\Core\Exceptions\Error;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use DateTime;
use Exception;

/**
 * Class Condition
 */
class Condition
{
    /**
     * @param ConditionGroup $condition
     *
     * @return bool
     * @throws Error
     */
    public static function isCheck(ConditionGroup $condition): bool
    {
        $method = 'check' . ucfirst($condition->getType());

        if (method_exists(self::class, $method)) {
            return self::{$method}($condition->getValues());
        } else {
            throw new Error("Type {$condition->getType()} does not exists");
        }
    }

    /**
     * @param Entity $entity
     * @param array $items
     *
     * @return ConditionGroup
     * @throws Error
     */
    public static function prepare(Entity $entity, array $items): ConditionGroup
    {
        if (empty($items)) {
            throw new Error('Empty items in condition');
        }
        $result = null;
        if (isset($items['type'])) {
            if ($items['type'] != 'and' && $items['type'] != 'or' && $items['type'] != 'not') {
                $result = self::prepareConditionGroup($entity, $items);
            } elseif ($items['type'] == 'not') {
                $result = new ConditionGroup($items['type'], [self::prepare($entity, $items['value'])]);
            } else {
                if (empty($items['value'])) {
                    throw new Error('Empty value or in condition');
                }
                $valuesConditionGroup = [];
                foreach ($items['value'] as $value) {
                    $valuesConditionGroup[] = self::prepare($entity, $value);
                }
                $result = new ConditionGroup($items['type'], $valuesConditionGroup);
            }
        } else {
            $type = 'and';
            $valuesConditionGroup = [];
            foreach ($items as $value) {
                $valuesConditionGroup[] = self::prepare($entity, $value);
            }
            $result = new ConditionGroup($type, $valuesConditionGroup);
        }
        return $result;
    }

    /**
     * @param Entity $entity
     * @param array $item
     *
     * @return ConditionGroup
     * @throws Error
     */
    private static function prepareConditionGroup(Entity $entity, array $item): ConditionGroup
    {
        if (!isset($item['attribute'])) {
            throw new Error('Empty attribute or in condition');
        }

        $attribute = $item['attribute'];

        if (!$entity->hasAttribute($attribute) && !$entity->hasRelation($attribute)) {
            throw new Error("Attribute '{$attribute}' does not exists in '{$entity->getEntityType()}'");
        }

        $currentValue = $entity->get($attribute);

        if (is_null($currentValue)
            && !empty($item['data']['field'])
            && $entity->get($item['data']['field'])) {
            $currentValue = $entity->get($item['data']['field']);
        }

        if ($currentValue instanceof EntityCollection) {
            $currentValue = array_column($currentValue->toArray(), 'id');
        }

        $values[] = $currentValue;
        if (isset($item['value'])) {
            $values[] = $item['value'];
        }

        return new ConditionGroup($item['type'], $values);
    }

    /**
     * @param array $values Array containing the necessary value
     *      $values = [
     *          0   => (ConditionGroup)
     *          1   => (ConditionGroup)
     *          .....
     *          n   => (ConditionGroup)
     *      ]
     * @return bool
     * @throws Error
     */
    protected static function checkAnd(array $values): bool
    {
        $result = true;

        foreach ($values as $value) {
            $result = self::isCheck($value);
            if (!$result) {
                break;
            }
        }

        return $result;
    }

    /**
     * @param array $values Array containing the necessary value
     *      $values = [
     *          0   => (ConditionGroup)
     *          1   => (ConditionGroup)
     *          .....
     *          n   => (ConditionGroup)
     *      ]
     * @return bool
     * @throws Error
     */
    protected static function checkOr(array $values): bool
    {
        $result = false;

        foreach ($values as $value) {
            $result = self::isCheck($value);
            if ($result) {
                break;
            }
        }
        return $result;
    }

    /**
     * @param array $values Array containing the necessary value
     *      $values = [
     *          0   => (ConditionGroup)
     *      ]
     * @return bool
     * @throws Error
     */
    protected static function checkNot(array $values): bool
    {
        self::isValidCountArray(1, $values);

        return !self::isCheck(array_shift($values));
    }

    /**
     * @param array $values Array containing the necessary value
     *      $values = [
     *          0   => (string|array|float|null|int)
     *          0   => (string|array|float|null|int)
     *      ]
     * @return bool
     * @throws Error
     */
    protected static function checkEquals(array $values): bool
    {
        self::isValidCountArray(2, $values);

        $left = array_shift($values);
        $right = array_shift($values);

        if ($left instanceof Entity) {
            $left = $left->get('id');
        } elseif (self::isScalar(gettype($left)) && gettype($left) !== gettype($right)) {
            settype($right, gettype($left));
        }

        return $left === $right;
    }

    /**
     * @param array $values Array containing the necessary value
     *      $values = [
     *          0   => (string|array|float|null|int)
     *          0   => (string|array|float|null|int)
     *      ]
     * @return bool
     * @throws Error
     */
    protected static function checkNotEquals(array $values): bool
    {
        return !self::checkEquals($values);
    }

    /**
     * @param array $values Array containing the necessary value
     *      $values = [
     *          0   => (string|array|null)
     *      ]
     * @return bool
     * @throws Error
     */
    protected static function checkIsEmpty(array $values)
    {
        self::isValidCountArray(1, $values);

        $value = array_shift($values);

        return is_null($value) || $value === '' || $value === [];
    }

    /**
     * @param array $values Array containing the necessary value
     *      $values = [
     *          0   => (string|array|null)
     *      ]
     * @return bool
     * @throws Error
     */
    protected static function checkIsNotEmpty(array $values)
    {
        return !self::checkIsEmpty($values);
    }

    /**
     * @param array $values Array containing the necessary value
     *      $values = [
     *          0   => (bool)
     *      ]
     * @return bool
     * @throws Error
     */
    protected static function checkIsTrue(array $values): bool
    {
        self::isValidCountArray(1, $values);

        return (bool)array_shift($values);
    }

    /**
     * @param array $values Array containing the necessary value
     *      $values = [
     *          0   => (bool)
     *      ]
     * @return bool
     * @throws Error
     */
    protected static function checkIsFalse(array $values): bool
    {
        return !self::checkIsTrue($values);
    }

    /**
     * @param array $values Array containing the necessary value
     *      $values = [
     *          0   => (EntityCollection|array)
     *          1   => (string|int|float|bool|null)
     *      ]
     * @return bool
     * @throws Error
     */
    protected static function checkContains(array $values): bool
    {
        self::isValidCountArray(2, $values);

        $currentValue = array_shift($values);
        self::isValidFirstValueIsArray($currentValue);

        $needValue = array_shift($values);
        self::isValidNotArrayAndObject($needValue);

        return in_array($needValue, $currentValue);
    }

    /**
     * @param array $values Array containing the necessary value
     *      $values = [
     *          0   => (EntityCollection|array)
     *          1   => (string|int|float|bool|null)
     *      ]
     * @return bool
     * @throws Error
     */
    protected static function checkNotContains(array $values): bool
    {
        return !self::checkContains($values);
    }

    /**
     * @param array $values Array containing the necessary value
     *      $values = [
     *          0   => (array)
     *          1   => (string|int|float|bool|null)
     *      ]
     * @return bool
     * @throws Error
     */
    protected static function checkHas(array $values): bool
    {
        return self::checkContains($values);
    }

    /**
     * @param array $values Array containing the necessary value
     *      $values = [
     *          0   => (array)
     *          1   => (string|int|float|bool|null)
     *      ]
     * @return bool
     * @throws Error
     */
    protected static function checkNotHas(array $values): bool
    {
        return !self::checkHas($values);
    }

    /**
     * @param array $values Array containing the necessary value
     *      $values = [
     *          0   => (float|int) First numeric
     *          1   => (float|int) Second numeric
     *      ]
     * @return bool
     * @throws Error
     */
    protected static function checkGreaterThan(array $values): bool
    {
        self::isValidCountArray(2, $values);

        $currentValue = array_shift($values);
        $needValue = array_shift($values);

        return (float)$currentValue > (float)$needValue;
    }

    /**
     * @param array $values Array containing the necessary value
     *      $values = [
     *          0   => (float|int) First numeric
     *          1   => (float|int) Second numeric
     *      ]
     * @return bool
     * @throws Error
     */
    protected static function checkLessThan(array $values): bool
    {
        self::isValidCountArray(2, $values);

        $currentValue = array_shift($values);
        $needValue = array_shift($values);

        return (float)$currentValue < (float)$needValue;
    }

    /**
     * @param array $values Array containing the necessary value
     *      $values = [
     *          0   => (float|int) First numeric
     *          1   => (float|int) Second numeric
     *      ]
     * @return bool
     * @throws Error
     */
    protected static function checkGreaterThanOrEquals(array $values): bool
    {
        self::isValidCountArray(2, $values);

        $currentValue = array_shift($values);
        $needValue = array_shift($values);

        return (float)$currentValue >= (float)$needValue;
    }


    /**
     * @param array $values Array containing the necessary value
     *      $values = [
     *          0   => (float|int) First numeric
     *          1   => (float|int) Second numeric
     *      ]
     * @return bool
     * @throws Error
     */
    protected static function checkLessThanOrEquals(array $values): bool
    {
        self::isValidCountArray(2, $values);

        $currentValue = array_shift($values);
        $needValue = array_shift($values);

        return (float)$currentValue <= (float)$needValue;
    }

    /**
     * @param array $values Array containing the necessary value
     *      $values = [
     *          0   => (int|string|float|bool|null)
     *          1   => (array)
     *      ]
     *
     * @return bool
     * @throws Error
     */
    protected static function checkIn(array $values): bool
    {
        self::isValidCountArray(2, $values);

        $currentValue = array_shift($values);
        if (is_array($currentValue) || is_object($currentValue)) {
            throw new Error('The first value should not be an Array or Object type');
        }
        $needValue = array_shift($values);

        if (!is_array($needValue)) {
            throw new Error('The second value must be an Array type');
        }
        return in_array($currentValue, $needValue);
    }

    /**
     * @param array $values Array containing the necessary value
     *      $values = [
     *          0   => (int|string|float|bool|null)
     *          1   => (array)
     *      ]
     * @return bool
     * @throws Error
     */
    protected static function checkNotIn(array $values): bool
    {
        return !self::checkIn($values);
    }

    /**
     * @param array $values Array containing the necessary value
     *      $values = [
     *          0   => (string|DataTime) Time.
     *      ]
     * @return bool
     * @throws Error
     * @throws Exception
     */
    protected static function checkIsToday(array $values): bool
    {
        self::isValidCountArray(1, $values);
        $currentValue = array_shift($values);
        $result = false;
        if (!is_null($currentValue)) {
            self::isValidDateTime($currentValue);

            $time = (int)self::howTime($currentValue)->format("%R%a");
            $result = $time === 0;
        }
        return $result;
    }

    /**
     * @param array $values Array containing the necessary value
     *      $values = [
     *          0   => (string|DataTime) Time.
     *      ]
     * @return bool
     * @throws Error
     * @throws Exception
     */
    protected static function checkInFuture(array $values): bool
    {
        self::isValidCountArray(1, $values);
        $currentValue = array_shift($values);
        $result = false;
        if (!is_null($currentValue)) {
            self::isValidDateTime($currentValue);

            $time = (int)self::howTime($currentValue)->format("%R%d%h%i%s");
            $result = $time > 0;
        }
        return $result;
    }

    /**
     * @param array $values Array containing the necessary value
     *      $values = [
     *          0   => (string|DataTime) Time.
     *      ]
     * @return bool
     * @throws Error
     * @throws Exception
     */
    protected static function checkInPast(array $values): bool
    {
        self::isValidCountArray(1, $values);
        $currentValue = array_shift($values);
        $result = false;
        if (!is_null($currentValue)) {
            self::isValidDateTime($currentValue);

            $time = (int)self::howTime($currentValue)->format("%R%d%h%i%s");
            $result = $time < 0;
        }
        return $result;
    }

    /**
     * @param string| DateTime $time
     * @return DateInterval
     * @throws Exception
     */
    private static function howTime($time): DateInterval
    {
        $compareTime = $time instanceof DateTime
            ? $time
            : new DateTime($time);

        $today = new DateTime();
        if (strlen($time) <= 10) {
            $today->setTime(0, 0, 0);
        }
        return $today
            ->diff($compareTime);
    }

    /**
     * @param $value
     *
     * @return bool
     * @throws Error
     */
    private static function isValidNotArrayAndObject($value): bool
    {
        if (is_array($value) || is_object($value)) {
            throw new Error('The second value should not be an Array or Object type');
        }

        return true;
    }

    /**
     * @param int $needCount
     * @param array $values
     *
     * @return bool
     * @throws Error
     */
    private static function isValidCountArray(int $needCount, array $values): bool
    {
        if (count($values) < $needCount) {
            throw new Error("Wrong number of values");
        }

        return true;
    }

    /**
     * @param $time
     *
     * @return bool
     * @throws Error
     */
    private static function isValidDateTime($time): bool
    {
        if (!is_string($time) && !$time instanceof DateTime) {
            throw new Error('The first value must be an string or DateTime type');
        }

        return true;
    }

    /**
     * @param $value
     *
     * @return bool
     * @throws Error
     */
    private static function isValidFirstValueIsArray($value): bool
    {
        if (!is_array($value)) {
            throw new Error('The first value must be an Array type');
        }

        return true;
    }

    /**
     * @param string $type
     *
     * @return bool
     */
    public static function isScalar(string $type): bool
    {
        return in_array($type, ['boolean', 'integer', 'double', 'string']);
    }
}
