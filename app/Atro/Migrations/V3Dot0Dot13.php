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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

/**
 * Moves the system parameters from data/config.php to data/config.system.php. A data/config.system.php that
 * already exists is left as it is: only the parameters it holds with the same values are removed from data/config.php.
 */
class V3Dot0Dot13 extends Base
{
    private const string CONFIG_PATH = 'data/config.php';
    private const string SYSTEM_CONFIG_PATH = 'data/config.system.php';

    private const SYSTEM_KEYS
        = [
            'isInstalled',
            'database',
            'logger',
            'amountOfDbDumps',
            'filesPath',
            'thumbnailsPath',
            'encryptionKey',
            'passwordPepper',
            'oauthEncryptionKey',
        ];

    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-11-24 12:00:00');
    }

    public function up(): void
    {
        if (!file_exists(self::CONFIG_PATH)) {
            return;
        }

        $config = self::read(self::CONFIG_PATH);
        $keys = array_flip(self::SYSTEM_KEYS);

        // prepared by the administrator or left by an interrupted migration
        if (file_exists(self::SYSTEM_CONFIG_PATH)) {
            $system = self::read(self::SYSTEM_CONFIG_PATH);

            $duplicates = [];
            foreach (array_intersect_key($config, $system, $keys) as $key => $value) {
                if ($value === $system[$key]) {
                    $duplicates[$key] = true;
                }
            }

            if (!empty($duplicates)) {
                self::write(self::CONFIG_PATH, array_diff_key($config, $duplicates));
            }

            return;
        }

        // the system file first: an interrupted migration leaves the keys in both files, with the same values
        self::write(self::SYSTEM_CONFIG_PATH, array_intersect_key($config, $keys));
        self::write(self::CONFIG_PATH, array_diff_key($config, $keys));
    }

    private static function read(string $path): array
    {
        $data = include $path;
        if (!is_array($data)) {
            throw new \RuntimeException(sprintf('Config file %s is corrupted.', $path));
        }

        return $data;
    }

    /**
     * Written through a temporary file that is renamed into place, with the permissions of data/config.php
     */
    private static function write(string $path, array $data): void
    {
        $tmp = $path . '.' . uniqid('', true) . '.tmp';

        if (file_put_contents($tmp, "<?php\nreturn " . self::export($data) . ";\n") === false) {
            throw new \RuntimeException(sprintf('Cannot write %s.', $tmp));
        }

        $perms = fileperms(self::CONFIG_PATH);
        if ($perms !== false) {
            chmod($tmp, $perms & 0777);
        }

        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException(sprintf('Cannot write %s.', $path));
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($path, true);
        }
    }

    private static function export(mixed $value, int $level = 0): string
    {
        if ($value instanceof \stdClass) {
            return '(object) ' . self::export(get_object_vars($value), $level);
        }

        if (!is_array($value)) {
            return var_export($value, true);
        }

        if ($value === []) {
            return '[]';
        }

        $indent = str_repeat('    ', $level + 1);

        $rows = [];
        foreach ($value as $key => $item) {
            $rows[] = $indent . var_export($key, true) . ' => ' . self::export($item, $level + 1);
        }

        return "[\n" . implode(",\n", $rows) . "\n" . str_repeat('    ', $level) . ']';
    }
}
