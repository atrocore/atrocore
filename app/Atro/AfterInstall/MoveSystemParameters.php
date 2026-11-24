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

namespace Atro\AfterInstall;

use Atro\Core\ModuleManager\AfterInstallInterface;
use Atro\Core\OAuth\Keys;
use Atro\Core\Utils\PasswordHash;

/**
 * Moves the system parameters the installer has written to data/config.php to data/config.system.php.
 *
 * A data/config.system.php prepared by hand is never written, as writing it would replace its expressions,
 * e.g. getenv(), with their values: what it defines is only removed from data/config.php, where it is
 * overridden anyway.
 */
class MoveSystemParameters implements AfterInstallInterface
{
    private const string CONFIG_PATH = 'data/config.php';
    private const string SYSTEM_CONFIG_PATH = 'data/config.system.php';

    private const SYSTEM_KEYS
        = [
            'database',
            'logger',
            'amountOfDbDumps',
            'filesPath',
            'encryptionKey',
            PasswordHash::PEPPER_CONFIG_KEY,
            Keys::ENCRYPTION_KEY_CONFIG_KEY,
        ];

    public static function getSortOrder(): int
    {
        return 100;
    }

    public function run(): void
    {
        if (!file_exists(self::CONFIG_PATH)) {
            return;
        }

        $config = self::read(self::CONFIG_PATH);
        $system = file_exists(self::SYSTEM_CONFIG_PATH) ? self::read(self::SYSTEM_CONFIG_PATH) : [];
        $keys = array_flip(self::SYSTEM_KEYS);

        if (self::isGenerated($system)) {
            // the values of data/config.php are the latest ones: the installer has just written them
            $moved = array_intersect_key($config, $keys);
            if (empty($moved)) {
                return;
            }

            // the system file first: an interruption leaves the parameters in both files, with the same values
            self::write(self::SYSTEM_CONFIG_PATH, array_merge($system, $moved));
            self::write(self::CONFIG_PATH, array_diff_key($config, $moved));

            return;
        }

        $data = self::removeOverridden($config, array_intersect_key($system, $keys));
        if ($data !== $config) {
            self::write(self::CONFIG_PATH, $data);
        }
    }

    /**
     * Whether data/config.system.php is missing or exactly what writing its values gives, i.e. holds plain values
     * only. Anything else - an expression, a comment, another formatting - means the file is prepared by hand.
     */
    private static function isGenerated(array $system): bool
    {
        if (!file_exists(self::SYSTEM_CONFIG_PATH)) {
            return true;
        }

        return file_get_contents(self::SYSTEM_CONFIG_PATH) === self::render($system);
    }

    /**
     * Associative arrays of data/config.system.php override data/config.php key by key, anything else as a whole
     */
    private static function removeOverridden(array $data, array $system): array
    {
        foreach ($system as $key => $value) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            if (self::isMap($value) && self::isMap($data[$key])) {
                $data[$key] = self::removeOverridden($data[$key], $value);
                if ($data[$key] === []) {
                    unset($data[$key]);
                }
            } else {
                unset($data[$key]);
            }
        }

        return $data;
    }

    private static function isMap(mixed $value): bool
    {
        return is_array($value) && $value !== [] && !array_is_list($value);
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

        if (file_put_contents($tmp, self::render($data)) === false) {
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

    private static function render(array $data): string
    {
        return "<?php\nreturn " . self::export($data) . ";\n";
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
