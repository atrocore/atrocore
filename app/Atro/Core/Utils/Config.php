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

class Config extends \Espo\Core\Utils\Config
{
    protected ?array $referenceData = null;

    public function clearReferenceDataCache(): void
    {
        $this->referenceData = null;
    }

    protected function loadConfig($reload = false)
    {
        parent::loadConfig($reload);

        // put reference data into config
        $this->putReferenceDataIntoConfig();

        return $this->data;
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
                        $this->data['locales'][$row['id']] = [
                            'name'              => $row['name'],
                            'language'          => $row['code'] ?? 'en_US',
                            'fallbackLanguage'  => $row['fallbackLanguageCode'] ?? null,
                            'weekStart'         => $row['weekStart'] === 'monday' ? 1 : 0,
                            'dateFormat'        => $row['dateFormat'] ?? 'MM/DD/YYYY',
                            'timeFormat'        => $row['timeFormat'] ?? 'HH:mm',
                            'timeZone'          => $row['timeZone'] ?? 'UTC',
                            'thousandSeparator' => $row['thousandSeparator'] ?? '',
                            'decimalMark'       => $row['decimalMark'] ?? '.',
                        ];
                    }
                    break;
                case 'Language':
                    foreach ($items as $row) {
                        if ($row['role'] === 'main') {
                            $this->data['mainLanguage'] = $row['code'];
                        } elseif ($row['role'] === 'additional') {
                            $this->data['inputLanguageList'][] = $row['code'];
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