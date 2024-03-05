<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Core\FileStorage;

use Atro\Core\Container;
use Atro\Entities\File;
use Atro\Entities\Storage;
use Espo\ORM\EntityManager;

class FileSystem implements FileStorageInterface
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function scan(Storage $storage): void
    {
        $files = $this->getDirFiles(trim($storage->get('path'), '/'));

        echo '<pre>';
        print_r($files);
        die();

//        foreach ($files as $file) {
////            $this->getEntityManager()->getRepository('File')->get();
////            echo '<pre>';
////            print_r(pathinfo($file));
////            die();
//        }


    }

    public function getDirFiles(string $dir, &$results = [])
    {
        foreach (scandir($dir) as $value) {
            if ($value === "." || $value === "..") {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $value;
            if (is_file($path)) {
                $results[] = $path;
            } elseif (is_dir($path)) {
                $this->getDirFiles($path, $results);
            }
        }

        return $results;
    }

    public function delete(File $file): void
    {
        //@todo
    }

    public function getLocalPath(File $file): string
    {
        //@todo
        return '';
    }

    public function getUrl(File $file): string
    {
        //@todo
        return '';
    }

    public function getThumbnailUrl(File $file, string $type): string
    {
        //@todo
        return '';
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }
}