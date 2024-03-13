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

class MemoryStorage implements StorageInterface
{
    private array $cacheData = [];

    public function set(string $key, $value, int $expiration = 0): void
    {
        $this->cacheData[$key] = $value;
    }

    public function get(string $key)
    {
        return $this->cacheData[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->cacheData);
    }

    public function delete(string $key): void
    {
        if (array_key_exists($key, $this->cacheData)) {
            unset($this->cacheData[$key]);
        }
    }

    public function getKeys(): array
    {
        return array_keys($this->cacheData);
    }
}