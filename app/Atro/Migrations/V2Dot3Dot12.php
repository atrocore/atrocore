<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V2Dot3Dot12 extends Base
{
    public const INSTALLER_FILE = 'atrocore-installer.phar';
    public const INSTALLER_VERSION = '1.0.3';
    public const INSTALLER_URL = 'https://packagist.atrocore.com/installer/download/%s/atrocore-installer.phar';

    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-07-30 12:00:00');
    }

    public function up(): void
    {
        $this->downloadInstaller();
    }

    /**
     * `composer.phar` was replaced with `atrocore-installer.phar`, so the installer has to be put into the project root.
     * Any further updates of the installer itself are done via its own `self-update` command.
     */
    protected function downloadInstaller(): void
    {
        if (file_exists(self::INSTALLER_FILE)) {
            return;
        }

        $url = sprintf(self::INSTALLER_URL, self::INSTALLER_VERSION);
        $tmpFile = self::INSTALLER_FILE . '.tmp';

        try {
            $this->download($url, $tmpFile);
            rename($tmpFile, self::INSTALLER_FILE);
            @chmod(self::INSTALLER_FILE, 0755);
        } catch (\Throwable $e) {
            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }

            $message = "The installer could not be downloaded: {$e->getMessage()}. Please download it from '$url' and put it into the project root manually.";

            if (isset($GLOBALS['log'])) {
                $GLOBALS['log']->error($message);
            }

            echo $message . PHP_EOL;
        }
    }

    protected function download(string $url, string $filePath): void
    {
        $fp = fopen($filePath, 'w+');
        if ($fp === false) {
            throw new \Exception("Can't write to the file '$filePath'");
        }

        set_time_limit(0);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch);
        $error = curl_error($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (!empty($error)) {
            throw new \Exception($error);
        }

        if ($responseCode !== 200) {
            throw new \Exception("'$url' responded with the code $responseCode");
        }

        if (!$this->isPhar($filePath)) {
            throw new \Exception("The downloaded file is not a valid PHAR archive");
        }
    }

    protected function isPhar(string $filePath): bool
    {
        $fp = @fopen($filePath, 'r');
        if ($fp === false) {
            return false;
        }

        $header = fread($fp, 64);
        fclose($fp);

        return is_string($header) && str_contains($header, '<?php') && str_contains($header, 'Phar::mapPhar');
    }
}