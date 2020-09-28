<?php

declare(strict_types=1);

namespace Treo\Core\Utils;

use Espo\Core\Utils\Util as Base;
use FilesystemIterator;

/**
 * Class Util
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class Util extends Base
{
    /**
     * @param string $dir
     *
     * @return array
     */
    public static function scanDir(string $dir): array
    {
        // prepare result
        $result = [];

        if (file_exists($dir) && is_dir($dir)) {
            foreach (scandir($dir) as $item) {
                if (!in_array($item, ['.', '..'])) {
                    $result[] = $item;
                }
            }
        }

        return $result;
    }

    /**
     * Remove dir recursively
     *
     * @param string $dir
     */
    public static function removeDir(string $dir)
    {
        if (file_exists($dir) && is_dir($dir)) {
            foreach (self::scanDir($dir) as $object) {
                if (is_dir($dir . "/" . $object)) {
                    self::removeDir($dir . "/" . $object);
                } else {
                    unlink($dir . "/" . $object);
                }
            }
            rmdir($dir);
        }
    }

    /**
     * Copy dir recursively
     *
     * @param string $src
     * @param string $dest
     *
     * @return mixed
     */
    public static function copyDir(string $src, string $dest)
    {
        if (!is_dir($src)) {
            return false;
        }

        if (!is_dir($dest)) {
            if (!mkdir($dest)) {
                return false;
            }
        }

        $i = new \DirectoryIterator($src);
        foreach ($i as $f) {
            if ($f->isFile()) {
                copy($f->getRealPath(), "$dest/" . $f->getFilename());
            } else {
                if (!$f->isDot() && $f->isDir()) {
                    self::copyDir($f->getRealPath(), "$dest/$f");
                }
            }
        }
    }

    /**
     * Get count folders and files in folder
     *
     * @param $folder
     *
     * @return int
     */
    public static function countItems($folder)
    {
        if (!is_dir($folder)) {
            return 0;
        }
        $fi = new FilesystemIterator($folder, FilesystemIterator::SKIP_DOTS);
        return iterator_count($fi);
    }
}
