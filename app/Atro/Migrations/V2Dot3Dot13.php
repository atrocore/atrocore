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

class V2Dot3Dot13 extends Base
{
    public const PACKAGIST_URL = 'https://packagist.atrocore.com/packages.json';

    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-07-30 17:00:00');
    }

    public function up(): void
    {
        if (file_exists('composer.phar')) {
            unlink('composer.phar');
        }

        $this->cleanupComposerJson();
    }

    /**
     * The AtroCore repository, the post update script and the plugins permissions are handled
     * by `atrocore-installer.phar` itself now, so they have to be removed from the composer.json.
     */
    protected function cleanupComposerJson(): void
    {
        foreach (['composer.json', 'data/stable-composer.json'] as $filename) {
            if (!file_exists($filename)) {
                continue;
            }

            $data = @json_decode(file_get_contents($filename), true);
            if (!is_array($data)) {
                continue;
            }

            $modified = false;

            if (!empty($data['repositories']) && is_array($data['repositories'])) {
                foreach ($data['repositories'] as $key => $repository) {
                    // the ID in the URL is different for every system, so only the beginning of the URL is checked
                    if (is_array($repository) && str_starts_with((string)($repository['url'] ?? ''), self::PACKAGIST_URL)) {
                        unset($data['repositories'][$key]);
                        $modified = true;
                    }
                }

                if ($modified) {
                    $data['repositories'] = array_values($data['repositories']);
                    if (empty($data['repositories'])) {
                        unset($data['repositories']);
                    }
                }
            }

            if (isset($data['scripts']['post-update-cmd'])) {
                unset($data['scripts']['post-update-cmd']);
                $modified = true;

                if (empty($data['scripts'])) {
                    unset($data['scripts']);
                }
            }

            if (isset($data['config']['allow-plugins']['php-http/discovery'])) {
                unset($data['config']['allow-plugins']['php-http/discovery']);
                $modified = true;

                if (empty($data['config']['allow-plugins'])) {
                    unset($data['config']['allow-plugins']);
                }

                if (empty($data['config'])) {
                    unset($data['config']);
                }
            }

            if ($modified) {
                file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        }
    }
}
