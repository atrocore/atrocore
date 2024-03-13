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

namespace Atro\Core\KeyValueStorages;

interface StorageInterface
{
    /**
     * @param string $key
     * @param mixed  $value
     * @param int    $expiration (seconds)
     *
     * @return void
     */
    public function set(string $key, $value, int $expiration = 0): void;

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function get(string $key);

    public function has(string $key): bool;

    public function delete(string $key): void;

    public function getKeys(): array;
}