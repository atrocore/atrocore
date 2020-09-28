<?php

namespace Espo\Core\Utils\File;
use Espo\Core\Exceptions\Error;

class ZipArchive
{
    private $fileManager;

    public function __construct(Manager $fileManager = null)
    {
        if (!isset($fileManager)) {
            $fileManager = new Manager();
        }

        $this->fileManager = $fileManager;
    }

    protected function getFileManager()
    {
        return $this->fileManager;
    }


    public function zip($sourcePath, $file)
    {

    }

    /**
     * Unzip archive
     *
     * @param  string $file  Path to .zip file
     * @param  [type] $destinationPath
     * @return bool
     */
    public function unzip($file, $destinationPath)
    {
        if (!class_exists('\ZipArchive')) {
            throw new Error("Class ZipArchive does not installed. Cannot unzip the file.");
        }

        $zip = new \ZipArchive;
        $res = $zip->open($file);

        if ($res === TRUE) {

            $this->getFileManager()->mkdir($destinationPath);

            $zip->extractTo($destinationPath);
            $zip->close();

            return true;
        }

        return false;
    }


}