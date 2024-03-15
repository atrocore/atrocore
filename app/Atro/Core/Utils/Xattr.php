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

class Xattr
{
    public function hasServerExtensions(): bool
    {
        return function_exists('xattr_get') || boolval(exec('which attr 2>/dev/null'));
    }

    public function get(string $file, string $key): ?string
    {
        if (!$this->hasServerExtensions()) {
            return null;
        }

        if (function_exists('xattr_get')) {
            return xattr_get($file, $key) ?: null;
        }

        $out = [];
        exec(sprintf('attr -qg %s %s 2>/dev/null', escapeshellarg($key), escapeshellarg($file)), $out);
        $out = trim(implode("\n", $out));

        return $out ?: null;
    }

    public function set(string $file, string $key, string $value): void
    {
        if (!$this->hasServerExtensions()) {
            return;
        }

        if (empty($value)) {
            $this->remove($file, $key);
            return;
        }

        if (function_exists('xattr_set')) {
            xattr_set($file, $key, $value);
            return;
        }

        exec(
            sprintf(
                'attr -qs %s -V %s %s 2>/dev/null',
                escapeshellarg($key),
                escapeshellarg($value),
                escapeshellarg($file)
            )
        );
    }

    public function remove(string $file, string $key): void
    {
        if (!$this->hasServerExtensions()) {
            return;
        }

        if (function_exists('xattr_remove')) {
            xattr_remove($file, $key);
            return;
        }

        exec(sprintf('attr -qr %s %s 2>/dev/null', escapeshellarg($key), escapeshellarg($file)));
    }

    public function list(string $file): ?array
    {
        if (!$this->hasServerExtensions()) {
            return null;
        }

        if (function_exists('xattr_list')) {
            return xattr_list($file);
        }

        $all = [];
        exec(sprintf('attr -ql %s 2>/dev/null', escapeshellarg($file)), $all);
        $security = [];
        exec(sprintf('attr -Sql %s 2>/dev/null', escapeshellarg($file)), $security);

        return array_values(array_diff($all, $security));
    }
}