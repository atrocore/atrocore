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

namespace Atro\Jobs;

use Atro\Core\Monolog\Handler\ReportingHandler;
use Atro\Core\Utils\Util;
use Atro\Entities\Job;
use Atro\Services\Composer;

class SendReports extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $exists = $this->getEntityManager()->getRepository('Job')
            ->where([
                'id!='   => $job->id,
                'type'   => 'SendReports',
                'status' => 'Running'
            ])
            ->findOne();

        if (!empty($exists)) {
            return;
        }

        if (!$this->getConfig()->get('reportingEnabled', false)) {
            return;
        }

        $dir = ReportingHandler::REPORTING_PATH;
        $tmpDir = 'data/reporting-tmp';

        while (is_dir($dir)) {
            $files = Util::scanDir($dir);
            if (empty($files[0])) {
                break;
            }

            $file = $files[0];

            $currentDate = new \DateTime();
            $reportDate = new \DateTime(str_replace('.log', '', $file));
            $interval = $reportDate->diff($currentDate);
            $diffInMinutes = $interval->days * 24 * 60 + $interval->h * 60 + $interval->i;

            if ($diffInMinutes > 1) {
                $originalFileName = $dir . DIRECTORY_SEPARATOR . $file;
                $fileName = $tmpDir . DIRECTORY_SEPARATOR . $file;

                Util::createDir($tmpDir);
                if (file_exists($originalFileName) && is_dir($tmpDir) && @rename($originalFileName, $fileName)) {
                    $handle = fopen($fileName, "r");
                    if ($handle) {
                        while (($line = fgets($handle)) !== false) {
                            $record = @json_decode($line, true);
                            if (is_array($record)) {
                                $url = "https://reporting.atrocore.com/push.php";
                                $postData = [
                                    'message'    => $record['message'],
                                    'level'      => $record['level'],
                                    'datetime'   => $record['datetime'],
                                    'instanceId' => (string)$this->getConfig()->get('appId'),
                                    'instance'   => [
                                        'phpVersion'     => phpversion(),
                                        'databaseDriver' => $this->getConfig()->get('database.driver'),
                                        'modules'        => [
                                            'Core' => Composer::getCoreVersion()
                                        ],
                                        'composerConfig' => file_exists('composer.json') ? json_decode(file_get_contents('composer.json'), true) : null
                                    ],
                                ];

                                foreach ($this->getContainer()->get('moduleManager')->getModules() as $id => $module) {
                                    if (!empty($module->getName())) {
                                        $postData['instance']['modules'][$module->getName()] = $module->getVersion();
                                    }
                                }

                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $url);
                                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
                                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_TIMEOUT_MS, 100);
                                curl_exec($ch);
                                curl_close($ch);
                            }
                        }
                        fclose($handle);
                    }
                    if (file_exists($fileName)) {
                        @unlink($fileName);
                    }
                }
            } else {
                break;
            }
        }
    }
}
