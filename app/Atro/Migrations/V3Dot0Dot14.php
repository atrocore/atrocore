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
 * Nothing is served from public/upload anymore: the thumbnails are stored outside the public directory and the
 * converted images are not stored at all. Their copies in the public directory are removed, as well as the directory
 * itself when nothing else is left in it, and the files no longer keep the path of their thumbnails.
 */
class V3Dot0Dot14 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-11-24 15:00:00');
    }

    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if ($toSchema->hasTable('file') && $toSchema->getTable('file')->hasColumn('thumbnails_path')) {
            $toSchema->getTable('file')->dropColumn('thumbnails_path');
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getDbal()->executeStatement($sql);
        }

        $this->removePublicDir((string)$this->getConfig()->get('thumbnailsPath', 'upload/thumbnails'));
        $this->removePublicDir((string)$this->getConfig()->get('renditionPath', 'upload/rendition'));

        // the directory protected by .htaccess is removed only when it holds nothing else
        if (is_dir('public/upload') && array_diff(scandir('public/upload') ?: [], ['.', '..', '.htaccess']) === []) {
            @unlink('public/upload/.htaccess');
            @rmdir('public/upload');
        }
    }

    protected function removePublicDir(string $path): void
    {
        $path = trim($path, '/');
        if ($path === '' || str_contains($path, '..')) {
            return;
        }

        self::removeDir('public/' . $path);
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) {
            return;
        }

        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $item) {
            $path = $dir . '/' . $item;
            if (is_dir($path) && !is_link($path)) {
                self::removeDir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
