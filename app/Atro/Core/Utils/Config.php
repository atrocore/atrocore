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

namespace Atro\Core\Utils;

use Atro\Core\Templates\Repositories\ReferenceData;
use Espo\Core\Utils\Util;

class Config extends \Espo\Core\Utils\Config
{
    /**
     * System config defaults, merged under data/config.php on load
     */
    protected array $systemConfig = [
        'defaultPermissions'        => [
            'dir'   => '0775',
            'file'  => '0664',
            'user'  => '',
            'group' => '',
        ],
        'jobMaxPortion'             => 15, /** Max number of jobs per one execution. */
        'jobPeriod'                 => 7800, /** Max execution time (in seconds) allocated for a sinle job. If exceeded then set to Failed.*/
        'jobPeriodForActiveProcess' => 36000, /** Max execution time (in seconds) allocated for a sinle job with active process. If exceeded then set to Failed.*/
        'jobRerunAttemptNumber'     => 1, /** Number of attempts to re-run failed jobs. */
        'crud'                      => [
            'get'    => 'read',
            'post'   => 'create',
            'put'    => 'update',
            'patch'  => 'patch',
            'delete' => 'delete',
        ],
        'systemItems'               => [
            'systemItems',
            'adminItems',
            'configPath',
            'cachePath',
            'database',
            'crud',
            'logger',
            'isInstalled',
            'defaultPermissions',
            'permissionMap',
            'permissionRules',
            'passwordSalt',
            'cryptKey',
            'userLimit',
            'stylesheet',
            'userItems',
        ],
        'adminItems'                => [
            'devMode',
            'jobMaxPortion',
            'jobPeriod',
            'jobRerunAttemptNumber',
            'adminPanelIframeUrl',
            'authTokenLifetime',
            'authTokenMaxIdleTime',
            'leadCaptureAllowOrigin',
            // secrets: must stay readable/writable for admins via Settings UI,
            // but hidden from non-admin API responses and script (Twig) contexts
            'smtpPassword',
            'oidcClientSecret',
            'gitlabApiToken',
            'oktaApiToken',
            'etimClientSecret',
            'icecatPassword',
        ],
        'userItems'                 => [
            'outboundEmailFromAddress',
            'integrations',
        ],
        'isInstalled'               => false,
    ];

    protected ?array $referenceData = null;

    public function clearReferenceDataCache(): void
    {
        $this->referenceData = null;
    }

    protected function loadConfig($reload = false)
    {
        // parent::loadConfig skips re-reading when data is already loaded — merge defaults only on actual load
        $justLoaded = $reload || empty($this->data);

        parent::loadConfig($reload);

        if ($justLoaded) {
            $this->data = Util::merge($this->systemConfig, $this->data);
        }

        // put reference data into config
        $this->putReferenceDataIntoConfig();

        $this->data['onlyStableReleases'] = \Atro\Services\Composer::getMinimumStability() === 'stable';

        return $this->data;
    }

    public function getData($isAdmin = null)
    {
        $res = parent::getData($isAdmin);

        foreach (['Translation', 'UiHandler'] as $entityName) {
            if (isset($res['referenceData'][$entityName])) {
                unset($res['referenceData'][$entityName]);
            }
        }

        if (isset($res['clickhouse']['database'])) {
            unset($res['clickhouse']['database']);
        }

        return $res;
    }

    protected function putReferenceDataIntoConfig(): void
    {
        $this->data['referenceData'] = [];
        $this->data['inputLanguageList'] = [];

        foreach ($this->getReferenceData() as $entityName => $items) {
            $this->data['referenceData'][$entityName] = $items;

            switch ($entityName) {
                case 'Locale':
                    foreach ($items as $row) {
                        if (!empty($row['id'])) {
                            $this->data['locales'][$row['id']] = [
                                'code'                           => $row['code'],
                                'name'                           => $row['name'] ?? 'en_US',
                                'language'                       => $row['languageCode'] ?? 'en_US',
                                'fallbackLanguage'               => $row['fallbackLanguageCode'] ?? null,
                                'weekStart'                      => $row['weekStart'] === 'monday' ? 1 : 0,
                                'dateFormat'                     => $row['dateFormat'] ?? 'MM/DD/YYYY',
                                'timeFormat'                     => $row['timeFormat'] ?? 'HH:mm',
                                'timeZone'                       => $row['timeZone'] ?? 'UTC',
                                'thousandSeparator'              => $row['thousandSeparator'] ?? '',
                                'decimalMark'                    => $row['decimalMark'] ?? '.',
                                'displayLabelsInContentLanguage' => $row['displayLabelsInContentLanguage'] ?? false,
                            ];
                        }

                    }
                    break;
                case 'Language':
                    foreach ($items as $row) {
                        if (!empty($row['role'])) {
                            if ($row['role'] === 'main') {
                                $this->data['mainLanguage'] = $row['code'];
                            } elseif ($row['role'] === 'additional') {
                                $this->data['inputLanguageList'][] = $row['code'];
                            }
                        }
                    }
                    break;
            }
        }

        $this->data['isMultilangActive'] = !empty($this->data['inputLanguageList']);
    }

    protected function getReferenceData(): array
    {
        if ($this->referenceData !== null) {
            return $this->referenceData;
        }

        $this->referenceData = [];

        if (is_dir(ReferenceData::DIR_PATH)) {
            foreach (scandir(ReferenceData::DIR_PATH) as $file) {
                if (!is_file(ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . $file)) {
                    continue;
                }
                $entityName = str_replace('.json', '', $file);
                $items = @json_decode(file_get_contents(ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . $file), true);
                if (!empty($items)) {
                    $this->referenceData[$entityName] = $items;
                }
            }
        }

        return $this->referenceData;
    }
}