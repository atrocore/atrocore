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

use Ramsey\Uuid\Uuid;
use Tuupola\Base32;

final class IdGenerator
{
    public static function uuid(): string
    {
        return (Uuid::uuid7())->toString();
    }

    public static function toUuid(string $value): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, $value);
    }

    public static function sortableId(): string
    {
        $crockford = new Base32([
            'characters' => Base32::CROCKFORD,
            'padding'    => false,
            'crockford'  => true,
        ]);

        $uuid = Uuid::uuid7();
        $bytes = str_pad($uuid->getBytes(), 20, "\x00", STR_PAD_LEFT);
        $encoded = $crockford->encode($bytes);

        return strtolower('a' . substr($encoded, 6));
    }

    public static function unsortableId(): string
    {
        return uniqid(strtolower(chr(rand(65, 90)))) . substr(md5((string)rand()), 0, 3);
    }
}
