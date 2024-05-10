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

class AtroHandler extends AbstractProcessingHandler
{
    protected string $instanceId;

    public function __construct($level, string $instanceId)
    {
        parent::__construct($level);

        $this->instanceId = $instanceId;
    }

    protected function write(array $record): void
    {
        $url = "https://reporting.atrocore.com/push.php";
        $postData = [
            'message'    => $record['message'],
            'level'      => $record['level'],
            'instanceId' => $this->instanceId
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    public function getDefaultFormatter(): FormatterInterface
    {
        return new NormalizerFormatter();
    }
}
