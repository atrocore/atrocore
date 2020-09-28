<?php

namespace Espo\Core\Utils\Log\Monolog\Handler;
use Monolog\Logger;

class RotatingFileHandler extends StreamHandler
{
    /**
     * Date format as a part of filename
     * @var string
     */
    protected $dateFormat = 'Y-m-d';

    /**
     * Filename format
     * @var string
     */
    protected $filenameFormat = '{filename}-{date}';

    protected $filename;

    protected $maxFiles;


    public function __construct($filename, $maxFiles = 0, $level = Logger::DEBUG, $bubble = true)
    {
        $this->filename = $filename;
        $this->maxFiles = (int) $maxFiles;

        parent::__construct($this->getTimedFilename(), $level, $bubble);

        $this->rotate();
    }

    public function setFilenameFormat($filenameFormat, $dateFormat)
    {
        $this->filenameFormat = $filenameFormat;
        $this->dateFormat = $dateFormat;
    }

    protected function rotate()
    {
        if (0 === $this->maxFiles) {
            return; //unlimited number of files for 0
        }

        $filePattern = $this->getFilePattern();
        $dirPath = $this->getFileManager()->getDirName($this->filename);
        $logFiles = $this->getFileManager()->getFileList($dirPath, false, $filePattern, true);

        if (!empty($logFiles) && count($logFiles) > $this->maxFiles) {

            usort($logFiles, function($a, $b) {
                return strcmp($b, $a);
            });

            $logFilesToBeRemoved = array_slice($logFiles, $this->maxFiles);

            $this->getFileManager()->removeFile($logFilesToBeRemoved, $dirPath);
        }
    }

    protected function getTimedFilename()
    {
        $fileInfo = pathinfo($this->filename);
        $timedFilename = str_replace(
            array('{filename}', '{date}'),
            array($fileInfo['filename'], date($this->dateFormat)),
            $fileInfo['dirname'] . '/' . $this->filenameFormat
        );

        if (!empty($fileInfo['extension'])) {
            $timedFilename .= '.'.$fileInfo['extension'];
        }

        return $timedFilename;
    }

    protected function getFilePattern()
    {
        $fileInfo = pathinfo($this->filename);
        $glob = str_replace(
            array('{filename}', '{date}'),
            array($fileInfo['filename'], '.*'),
            $this->filenameFormat
        );

        if (!empty($fileInfo['extension'])) {
            $glob .= '\.'.$fileInfo['extension'];
        }

        $glob = '^'.$glob.'$';

        return $glob;
    }
}