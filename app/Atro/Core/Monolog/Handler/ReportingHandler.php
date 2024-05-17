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

namespace Atro\Core\Monolog\Handler;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Espo\Core\Utils\File\Manager as FileManager;

class ReportingHandler extends AbstractProcessingHandler
{
    public const REPORTING_PATH = 'data/reporting';

    protected string $instanceId;
    protected FileManager $fileManager;
    private string $errorMessage;
    private int $maxErrorMessageLength = 5000;

    public function __construct($level, string $instanceId)
    {
        parent::__construct($level);

        $this->instanceId = $instanceId;
        $this->fileManager = new FileManager();
    }

    protected function write(array $record): void
    {
        $fileName = self::REPORTING_PATH . DIRECTORY_SEPARATOR . $record['datetime']->format('Y-m-d H:i:00') . '.log';

        if (!is_writable($fileName)) {
            $this->fileManager->checkCreateFile($fileName);
        }

        if (is_writable($fileName)) {
            set_error_handler([$this, 'customErrorHandler']);
            $this->fileManager->appendContents($fileName, $this->jsonMessage($record) . "\n");
            restore_error_handler();
        }

        if (isset($this->errorMessage)) {
            throw new \UnexpectedValueException(sprintf('The stream or file "%s" could not be opened: ' . $this->errorMessage, $fileName));
        }
    }

    private function jsonMessage(array $record): string
    {
        if (strlen($record['message']) > $this->maxErrorMessageLength) {
            $record['message'] = substr($record['message'], 0, $this->maxErrorMessageLength) . '...';
            $record['formatted'] = $this->getFormatter()->format($record);
        }

        return json_encode(
            [
                'level'    => $record['level'],
                'message'  => $record['formatted'],
                'datetime' => $record['datetime']->format('Y-m-d H:i:s T')
            ]
        );
    }

    private function customErrorHandler($code, $msg)
    {
        $this->errorMessage = $msg;
    }

    public function getDefaultFormatter(): FormatterInterface
    {
        return new NormalizerFormatter();
    }
}
