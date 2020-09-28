<?php

declare(strict_types=1);

namespace Treo\Core\Utils\File;

use Espo\Core\Exceptions\Error;

/**
 * Class Manager
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class Manager extends \Espo\Core\Utils\File\Manager
{
    /**
     * @inheritdoc
     */
    public function wrapForDataExport($content, $withObjects = false)
    {
        if (!isset($content) || !is_array($content)) {
            return false;
        }

        // prepare data
        $data = (!$withObjects) ? var_export($content, true) : $this->varExport($content);

        if ($data == '1' || $data == '0') {
            return false;
        }

        return "<?php\nreturn {$data};\n?>";
    }

    /**
     * @param $oldPath
     * @param $newPath
     * @param bool $removeEmptyDirs
     * @return bool
     * @throws Error
     */
    public function move($oldPath, $newPath, $removeEmptyDirs = true): bool
    {
        if (!file_exists($oldPath)) {
            throw new Error("File not found");
        }

        if ($this->checkCreateFile($newPath) === false) {
            throw new Error('Permission denied for ' . $newPath);
        }

        if (!rename($oldPath, $newPath)) {
            return false;
        }

        if ($removeEmptyDirs) {
            $this->removeEmptyDirs($oldPath);
        }

        return true;
    }

    /**
     * @param $contents
     * @return string
     */
    public function createOnTemp($contents): string
    {
        $tmpfile = tempnam("", uniqid());

        if ($tmpfile && file_put_contents($tmpfile, $contents) !== false) {
            return $tmpfile;
        }

        return '';
    }
}
