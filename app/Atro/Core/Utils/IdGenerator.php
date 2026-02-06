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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Ramsey\Uuid\Uuid;
use Tuupola\Base32;

final class IdGenerator
{
    public function __construct(private readonly Container $container)
    {
    }

    public static function uuid(): string
    {
        return (Uuid::uuid7())->toString();
    }

    public function toUuid(string $value): string
    {
        $uuid = self::uuid();

        try {
            $this->getConnection()->createQueryBuilder()
                ->insert('id_map')
                ->setValue('id', ':id')
                ->setValue('value', ':value')
                ->setParameter('id', $uuid)
                ->setParameter('value', $value)
                ->executeQuery();
        } catch (UniqueConstraintViolationException $e) {
            $uuid = $this->getConnection()->createQueryBuilder()
                ->select('id')
                ->from('id_map')
                ->where('value = :value')
                ->setParameter('value', $value)
                ->fetchOne();

            if (!$uuid) {
                throw new \Error('Could not resolve UUID for value: ' . $value);
            }
        }

        return $uuid;
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

    protected function getConnection(): Connection
    {
        return $this->container->get('connection');
    }
}
