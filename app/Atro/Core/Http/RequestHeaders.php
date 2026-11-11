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

namespace Atro\Core\Http;

/**
 * Reads the headers of the current HTTP request that change what the system returns: the requested meta groups,
 * the language and the like. Outside of an HTTP request there are no headers.
 */
class RequestHeaders
{
    public static function get(string $name): ?string
    {
        try {
            $headers = \getallheaders();
        } catch (\Throwable $e) {
            $headers = [];
        }

        foreach ($headers as $k => $v) {
            if (strtolower($name) === strtolower($k)) {
                return $v;
            }
        }

        return null;
    }

    /**
     * Whether the response has to carry the meta of its records. The With-Meta-Groups header turns it on by itself,
     * an empty one turns it off.
     */
    public static function isMetaRequested(): bool
    {
        $groups = self::getRequestedMetaGroups();
        if ($groups !== null) {
            return !empty($groups);
        }

        $meta = self::get('With-Meta');
        if (empty($meta)) {
            return false;
        }

        return $meta == 1 || $meta == '1' || strtolower(trim($meta)) == 'true';
    }

    /**
     * Whether the response has to carry the given group of the meta, so that the group is not even calculated
     * when nobody asks for it.
     */
    public static function isMetaGroupRequested(string $group): bool
    {
        if (!self::isMetaRequested()) {
            return false;
        }

        $groups = self::getRequestedMetaGroups();

        return $groups === null || in_array($group, $groups, true);
    }

    /**
     * The groups of the meta listed by the With-Meta-Groups header, or null when the header is absent and every
     * group is returned.
     *
     * @return string[]|null
     */
    public static function getRequestedMetaGroups(): ?array
    {
        $header = self::get('With-Meta-Groups');
        if ($header === null) {
            return null;
        }

        $groups = [];
        foreach (explode(',', $header) as $group) {
            $group = trim($group);
            if ($group !== '') {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    /**
     * The names of the link and linkMultiple fields and attributes listed by the Meta-Linked-Fields header, whose
     * records the linkedRecords meta group carries. The value * stands for the link fields and the link and
     * linkMultiple attributes.
     *
     * @return string[]
     */
    public static function getRequestedLinkedFields(): array
    {
        $names = [];
        foreach (explode(',', (string)self::get('Meta-Linked-Fields')) as $name) {
            $name = trim($name);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * The language the values have to be returned in: the Language header, or the language set for the current
     * process in $GLOBALS['languagePrism'].
     */
    public static function getLanguagePrism(): ?string
    {
        $language = self::get('language');
        if (!empty($GLOBALS['languagePrism'])) {
            $language = $GLOBALS['languagePrism'];
        }

        return $language;
    }
}
