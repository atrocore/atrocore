<?php

namespace Espo\Core\Utils\Log\Monolog\Handler;
use Monolog\Logger;

class StreamHandler extends \Monolog\Handler\StreamHandler
{
    protected $fileManager;

    protected $maxErrorMessageLength = 5000;

    public function __construct($url, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($url, $level, $bubble);

        $this->fileManager = new \Espo\Core\Utils\File\Manager();
    }

    protected function getFileManager()
    {
        return $this->fileManager;
    }


    protected function write(array $record)
    {
        if (!$this->url) {
            throw new \LogicException('Missing logger path, the stream can not be opened. Please check logger options in the data/config.php.');
        }

        $this->errorMessage = null;

        if (!is_writable($this->url)) {
            $this->getFileManager()->checkCreateFile($this->url);
        }

        if (is_writable($this->url)) {
            set_error_handler(array($this, 'customErrorHandler'));
            $this->getFileManager()->appendContents($this->url, $this->pruneMessage($record));
            restore_error_handler();
        }

        if (isset($this->errorMessage)) {
            throw new \UnexpectedValueException(sprintf('The stream or file "%s" could not be opened: '.$this->errorMessage, $this->url));
        }
    }

    private function customErrorHandler($code, $msg)
    {
        $this->errorMessage = $msg;
    }

    /**
     * Cut the error message depends on maxErrorMessageLength
     *
     * @param  array  $record
     * @return string
     */
    protected function pruneMessage(array $record)
    {
        $message = (string) $record['message'];

        if (strlen($message) > $this->maxErrorMessageLength) {
            $record['message'] = substr($message, 0, $this->maxErrorMessageLength) . '...';
            $record['formatted'] = $this->getFormatter()->format($record);
        }

        return (string) $record['formatted'];
    }

}