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

namespace Atro\Core\SelectManagers;

use Atro\Core\Utils\Config;
use Atro\Core\Utils\IdGenerator;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Query\QueryConverter;

/**
 * Converts a where item on a single column into an ORM where part. Knows nothing about entities, so it serves
 * both entity fields and tables that are not entities.
 */
class ColumnWhereConverter
{
    public const DATETIME_FORMAT = 'Y-m-d H:i:s';

    public const TYPES = [
        'like',
        'notLike',
        'equals',
        'on',
        'startsWith',
        'endsWith',
        'contains',
        'notContains',
        'similar',
        'wordSimilar',
        'notEquals',
        'notOn',
        'greaterThan',
        'after',
        'lessThan',
        'before',
        'greaterThanOrEquals',
        'lessThanOrEquals',
        'in',
        'notIn',
        'isNull',
        'isNotNull',
        'ever',
        'isTrue',
        'isFalse',
        'today',
        'past',
        'future',
        'lastSevenDays',
        'lastXDays',
        'nextXDays',
        'olderThanXDays',
        'afterXDays',
        'currentMonth',
        'lastMonth',
        'nextMonth',
        'currentQuarter',
        'lastQuarter',
        'currentYear',
        'lastYear',
        'between',
        'arrayAnyOf',
        'arrayNoneOf',
        'arrayIsEmpty',
        'arrayIsNotEmpty',
    ];

    protected Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function isSupported(string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }

    public function convert(string $attribute, string $type, mixed $value, string $tableAlias = QueryConverter::TABLE_ALIAS): array
    {
        $part = [];

        switch ($type) {
            case 'like':
                $part[$attribute . '*'] = $value;
                break;

            case 'notLike':
                $part[$attribute . '!*'] = $value;
                break;

            case 'equals':
            case 'on':
                $part[$attribute . '='] = $value;
                break;

            case 'startsWith':
                $part[$attribute . '*'] = $value . '%';
                break;

            case 'endsWith':
                $part[$attribute . '*'] = '%' . $value;
                break;

            case 'contains':
                $part[$attribute . '*'] = '%' . $value . '%';
                break;

            case 'notContains':
                $part[$attribute . '!*'] = '%' . $value . '%';
                break;

            case 'similar':
                if (!is_null($value) && $value !== '') {
                    $threshold        = (float)$this->config->get('similarityThreshold', 1);
                    $colName          = Util::toUnderScore($attribute);
                    $paramVal         = 'sim_v_' . IdGenerator::unsortableId();
                    $paramThr         = 'sim_t_' . IdGenerator::unsortableId();
                    $part['innerSql'] = [
                        'sql'        => 'similarity(' . $tableAlias . '.' . $colName . ', :' . $paramVal . ') >= :' . $paramThr,
                        'parameters' => [$paramVal => $value, $paramThr => $threshold],
                    ];
                }
                break;

            case 'wordSimilar':
                if (!is_null($value) && $value !== '') {
                    $threshold        = (float)$this->config->get('similarityThreshold', 1);
                    $colName          = Util::toUnderScore($attribute);
                    $paramVal         = 'wsim_v_' . IdGenerator::unsortableId();
                    $paramThr         = 'wsim_t_' . IdGenerator::unsortableId();
                    $part['innerSql'] = [
                        'sql'        => 'word_similarity(:' . $paramVal . ', ' . $tableAlias . '.' . $colName . ') >= :' . $paramThr,
                        'parameters' => [$paramVal => $value, $paramThr => $threshold],
                    ];
                }
                break;

            case 'notEquals':
            case 'notOn':
                $part[$attribute . '!='] = $value;
                break;

            case 'greaterThan':
            case 'after':
                $part[$attribute . '>'] = $value;
                break;

            case 'lessThan':
            case 'before':
                $part[$attribute . '<'] = $value;
                break;

            case 'greaterThanOrEquals':
                $part[$attribute . '>='] = $value;
                break;

            case 'lessThanOrEquals':
                $part[$attribute . '<='] = $value;
                break;

            case 'in':
                $part[$attribute . '='] = $value;
                break;

            case 'notIn':
                $part[$attribute . '!='] = $value;
                break;

            case 'isNull':
                $part[$attribute . '='] = null;
                break;

            case 'isNotNull':
            case 'ever':
                $part[$attribute . '!='] = null;
                break;

            case 'isTrue':
                $part[$attribute . '='] = true;
                break;

            case 'isFalse':
                $part[$attribute . '='] = false;
                break;

            case 'today':
                $part[$attribute . '='] = date('Y-m-d');
                break;

            case 'past':
                $part[$attribute . '<'] = date('Y-m-d');
                break;

            case 'future':
                $part[$attribute . '>'] = date('Y-m-d');
                break;

            case 'lastSevenDays':
                $dt1 = new \DateTime();
                $dt2 = clone $dt1;
                $dt2->modify('-7 days');
                $part['AND'] = [
                    $attribute . '>=' => $dt2->format('Y-m-d'),
                    $attribute . '<=' => $dt1->format('Y-m-d'),
                ];
                break;

            case 'lastXDays':
                $dt1    = new \DateTime();
                $dt2    = clone $dt1;
                $number = strval(intval($value));

                $dt2->modify('-' . $number . ' days');
                $dt2->setTime(0, 0, 0);
                $dt1->setTime(23, 59, 59);
                $part['AND'] = [
                    $attribute . '>=' => $dt2->format(self::DATETIME_FORMAT),
                    $attribute . '<=' => $dt1->format(self::DATETIME_FORMAT),
                ];
                break;

            case 'nextXDays':
                $dt1    = new \DateTime();
                $dt2    = clone $dt1;
                $number = strval(intval($value));
                $dt2->modify('+' . $number . ' days');
                $dt1->setTime(0, 0, 0);
                $dt2->setTime(23, 59, 59);
                $part['AND'] = [
                    $attribute . '>=' => $dt1->format(self::DATETIME_FORMAT),
                    $attribute . '<=' => $dt2->format(self::DATETIME_FORMAT),
                ];
                break;

            case 'olderThanXDays':
                $dt1    = new \DateTime();
                $number = strval(intval($value));
                $dt1->modify('-' . $number . ' days');
                $part[$attribute . '<'] = $dt1->format('Y-m-d');
                break;

            case 'afterXDays':
                $dt1    = new \DateTime();
                $number = strval(intval($value));
                $dt1->modify('+' . $number . ' days');
                $part[$attribute . '>'] = $dt1->format('Y-m-d');
                break;

            case 'currentMonth':
                $dt          = new \DateTime();
                $part['AND'] = [
                    $attribute . '>=' => $dt->modify('first day of this month')->format('Y-m-d'),
                    $attribute . '<'  => $dt->add(new \DateInterval('P1M'))->format('Y-m-d'),
                ];
                break;

            case 'lastMonth':
                $dt          = new \DateTime();
                $part['AND'] = [
                    $attribute . '>=' => $dt->modify('first day of last month')->format('Y-m-d'),
                    $attribute . '<'  => $dt->add(new \DateInterval('P1M'))->format('Y-m-d'),
                ];
                break;

            case 'nextMonth':
                $dt          = new \DateTime();
                $part['AND'] = [
                    $attribute . '>=' => $dt->modify('first day of next month')->format('Y-m-d'),
                    $attribute . '<'  => $dt->add(new \DateInterval('P1M'))->format('Y-m-d'),
                ];
                break;

            case 'currentQuarter':
                $dt      = new \DateTime();
                $quarter = ceil($dt->format('m') / 3);
                $dt->modify('first day of January this year');
                $part['AND'] = [
                    $attribute . '>=' => $dt->add(new \DateInterval('P' . (($quarter - 1) * 3) . 'M'))->format('Y-m-d'),
                    $attribute . '<'  => $dt->add(new \DateInterval('P3M'))->format('Y-m-d'),
                ];
                break;

            case 'lastQuarter':
                $dt      = new \DateTime();
                $quarter = ceil($dt->format('m') / 3);
                $dt->modify('first day of January this year');
                $quarter--;
                if ($quarter == 0) {
                    $quarter = 4;
                    $dt->modify('-1 year');
                }
                $part['AND'] = [
                    $attribute . '>=' => $dt->add(new \DateInterval('P' . (($quarter - 1) * 3) . 'M'))->format('Y-m-d'),
                    $attribute . '<'  => $dt->add(new \DateInterval('P3M'))->format('Y-m-d'),
                ];
                break;

            case 'currentYear':
                $dt          = new \DateTime();
                $part['AND'] = [
                    $attribute . '>=' => $dt->modify('first day of January this year')->format('Y-m-d'),
                    $attribute . '<'  => $dt->add(new \DateInterval('P1Y'))->format('Y-m-d'),
                ];
                break;

            case 'lastYear':
                $dt          = new \DateTime();
                $part['AND'] = [
                    $attribute . '>=' => $dt->modify('first day of January last year')->format('Y-m-d'),
                    $attribute . '<'  => $dt->add(new \DateInterval('P1Y'))->format('Y-m-d'),
                ];
                break;

            case 'between':
                if (is_array($value)) {
                    $part['AND'] = [
                        $attribute . '>=' => $value[0],
                        $attribute . '<=' => $value[1],
                    ];
                }
                break;

            case 'arrayAnyOf':
                if (empty($value) || !is_array($value)) {
                    break;
                }
                foreach ($value as $v) {
                    $part['OR'][] = [$attribute . '*' => '%"' . $v . '"%'];
                }
                break;

            case 'arrayNoneOf':
                if (empty($value) || !is_array($value)) {
                    break;
                }

                $andRows['AND'] = [];
                foreach ($value as $v) {
                    $andRows['AND'][] = [$attribute . '!*' => '%"' . $v . '"%'];
                }

                $part['OR'] = [
                    [$attribute => null],
                    [$attribute => '[]'],
                    [$attribute => ''],
                    $andRows
                ];
                break;

            case 'arrayIsEmpty':
                $part['OR'] = [
                    [$attribute => null],
                    [$attribute => '[]'],
                    [$attribute => '']
                ];
                break;

            case 'arrayIsNotEmpty':
                $part['AND'] = [
                    [$attribute . '!=' => null],
                    [$attribute . '!=' => '[]'],
                    [$attribute . '!=' => '']
                ];
                break;
        }

        return $part;
    }

    /**
     * Turns a datetime where item given in the user time zone into an item with UTC values and absolute types,
     * which then is converted as a regular where item.
     */
    public function prepareDateTimeItem(array $item): ?array
    {
        $value    = null;
        $timeZone = 'UTC';

        $attribute = null;
        if (isset($item['field'])) {
            $attribute = $item['field'];
        }
        if (isset($item['attribute'])) {
            $attribute = $item['attribute'];
        }

        if (!$attribute) {
            return null;
        }
        if (empty($item['type'])) {
            return null;
        }
        if (!empty($item['value'])) {
            $value = $item['value'];
        }

        if (!empty($item['timeZone'])) {
            $timeZone = $item['timeZone'];
        }

        $type = $item['type'];

        if (empty($value) && in_array($type, ['on', 'before', 'after'])) {
            return null;
        }

        $where              = [];
        $where['attribute'] = $attribute;

        $dt = new \DateTime('now', new \DateTimeZone($timeZone));

        switch ($type) {
            case 'today':
                $where['type'] = 'between';
                $dt->setTime(0, 0, 0);
                $dt->setTimezone(new \DateTimeZone('UTC'));
                $from = $dt->format(self::DATETIME_FORMAT);
                $dt->modify('+1 day -1 second');
                $to             = $dt->format(self::DATETIME_FORMAT);
                $where['value'] = [$from, $to];
                break;
            case 'past':
                $where['type'] = 'before';
                $dt->setTimezone(new \DateTimeZone('UTC'));
                $where['value'] = $dt->format(self::DATETIME_FORMAT);
                break;
            case 'future':
                $where['type'] = 'after';
                $dt->setTimezone(new \DateTimeZone('UTC'));
                $where['value'] = $dt->format(self::DATETIME_FORMAT);
                break;
            case 'lastSevenDays':
                $where['type'] = 'between';

                $dtFrom = clone $dt;

                $dt->setTimezone(new \DateTimeZone('UTC'));
                $to = $dt->format(self::DATETIME_FORMAT);

                $dtFrom->modify('-7 day');
                $dtFrom->setTime(0, 0, 0);
                $dtFrom->setTimezone(new \DateTimeZone('UTC'));

                $from = $dtFrom->format(self::DATETIME_FORMAT);

                $where['value'] = [$from, $to];
                break;
            case 'lastXDays':
                $where['type'] = 'between';

                $dtFrom = clone $dt;

                $dt->setTimezone(new \DateTimeZone('UTC'));
                $to = $dt->format(self::DATETIME_FORMAT);

                $number = strval(intval($item['value']));
                $dtFrom->modify('-' . $number . ' day');
                $dtFrom->setTime(0, 0, 0);
                $dtFrom->setTimezone(new \DateTimeZone('UTC'));

                $from = $dtFrom->format(self::DATETIME_FORMAT);

                $where['value'] = [$from, $to];
                break;
            case 'nextXDays':
                $where['type'] = 'between';

                $dtTo = clone $dt;

                $dt->setTimezone(new \DateTimeZone('UTC'));
                $from = $dt->format(self::DATETIME_FORMAT);

                $number = strval(intval($item['value']));
                $dtTo->modify('+' . $number . ' day');
                $dtTo->setTime(23, 59, 59);
                $dtTo->setTimezone(new \DateTimeZone('UTC'));

                $to = $dtTo->format(self::DATETIME_FORMAT);

                $where['value'] = [$from, $to];
                break;
            case 'olderThanXDays':
                $where['type'] = 'before';
                $number        = strval(intval($item['value']));
                $dt->modify('-' . $number . ' day');
                $dt->setTime(0, 0, 0);
                $dt->setTimezone(new \DateTimeZone('UTC'));
                $where['value'] = $dt->format(self::DATETIME_FORMAT);
                break;
            case 'afterXDays':
                $where['type'] = 'after';
                $number        = strval(intval($item['value']));
                $dt->modify('+' . $number . ' day');
                $dt->setTime(0, 0, 0);
                $dt->setTimezone(new \DateTimeZone('UTC'));
                $where['value'] = $dt->format(self::DATETIME_FORMAT);
                break;
            case 'on':
                $where['type'] = 'between';

                $dt = new \DateTime($value, new \DateTimeZone($timeZone));
                $dt->setTimezone(new \DateTimeZone('UTC'));
                $from = $dt->format(self::DATETIME_FORMAT);
                $dt->modify('+1 day -1 second');
                $to             = $dt->format(self::DATETIME_FORMAT);
                $where['value'] = [$from, $to];
                break;
            case 'before':
                $where['type'] = 'before';
                $dt            = new \DateTime($value, new \DateTimeZone($timeZone));
                $dt->setTimezone(new \DateTimeZone('UTC'));
                $where['value'] = $dt->format(self::DATETIME_FORMAT);
                break;
            case 'after':
                $where['type'] = 'after';
                $dt            = new \DateTime($value, new \DateTimeZone($timeZone));
                $dt->setTimezone(new \DateTimeZone('UTC'));
                $where['value'] = $dt->format(self::DATETIME_FORMAT);
                break;
            case 'between':
                $where['type'] = 'between';
                if (is_array($value)) {
                    $dt = new \DateTime($value[0], new \DateTimeZone($timeZone));
                    $dt->setTimezone(new \DateTimeZone('UTC'));
                    $from = $dt->format(self::DATETIME_FORMAT);

                    $dt = new \DateTime($value[1], new \DateTimeZone($timeZone));
                    $dt->setTimezone(new \DateTimeZone('UTC'));
                    $to             = $dt->format(self::DATETIME_FORMAT);
                    $where['value'] = [$from, $to];
                }
                break;
            default:
                $where['type'] = $type;
                if (!empty($value) && is_string($value)) {
                    $dt = new \DateTime($value, new \DateTimeZone($timeZone));
                    $dt->setTimezone(new \DateTimeZone('UTC'));
                    $where['value'] = $dt->format(self::DATETIME_FORMAT);
                } else {
                    $where['value'] = $value;
                }
        }

        $where['dateTime'] = false;

        return $where;
    }
}
