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

namespace Atro\Core\Utils;

class DataUtil
{
    /**
     * Array item which means "append items to the existing list" instead of replacing it.
     */
    public const APPEND_MARKER = '__APPEND__';

    /**
     * Recursively converts stdClass objects (and any mixed nested structure) to plain arrays.
     */
    public static function toArray(mixed $data): array
    {
        return json_decode(json_encode($data), true) ?? [];
    }

    /**
     * Deep merges $overrideData into $data:
     * - objects are merged property by property;
     * - lists are replaced, unless the override list contains APPEND_MARKER, then its items are appended;
     * - scalars are replaced.
     */
    public static function merge(mixed $data, mixed $overrideData): mixed
    {
        if (is_object($overrideData)) {
            return self::mergeObject($data, $overrideData);
        }

        if (is_array($overrideData)) {
            return self::mergeArray($data, $overrideData);
        }

        if ($data === [] && empty($overrideData)) {
            return [];
        }

        return $overrideData;
    }

    private static function mergeObject(mixed $data, object $overrideData): object
    {
        if (!is_object($data)) {
            $data = new \stdClass();
        }

        foreach (get_object_vars($overrideData) as $key => $value) {
            $data->$key = isset($data->$key) ? self::merge($data->$key, $value) : self::removeAppendMarker($value);
        }

        return $data;
    }

    private static function mergeArray(mixed $data, array $overrideData): array
    {
        if (!in_array(self::APPEND_MARKER, $overrideData, true)) {
            return $overrideData;
        }

        if (empty($data)) {
            $data = [];
        } elseif (!is_array($data)) {
            $data = self::toArray($data);
        }

        foreach ($overrideData as $item) {
            if ($item !== self::APPEND_MARKER) {
                $data[] = $item;
            }
        }

        return $data;
    }

    private static function removeAppendMarker(mixed $data): mixed
    {
        if (is_object($data)) {
            foreach (get_object_vars($data) as $key => $value) {
                if ($value === self::APPEND_MARKER) {
                    unset($data->$key);
                } else {
                    $data->$key = self::removeAppendMarker($value);
                }
            }

            return $data;
        }

        if (is_array($data)) {
            $isRemoved = false;
            foreach ($data as $key => $value) {
                if ($value === self::APPEND_MARKER) {
                    unset($data[$key]);
                    $isRemoved = true;
                } else {
                    $data[$key] = self::removeAppendMarker($value);
                }
            }

            return $isRemoved ? array_values($data) : $data;
        }

        return $data;
    }
}
