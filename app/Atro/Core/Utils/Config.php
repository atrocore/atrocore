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
    public function get($name, $default = null)
    {
        if ($name === 'isModulesLoaded') {
            return $this->container->get('moduleManager')->isLoaded();
        }

        if ($name === 'interfaceLocales') {
            $res = $this->loadInterfaceLocales();
            return $res[$name] ?? null;
        }

        $keys = explode('.', $name);

        $lastBranch = $this->loadConfig();
        foreach ($keys as $keyName) {
            if (isset($lastBranch[$keyName]) && (is_array($lastBranch) || is_object($lastBranch))) {
                if (is_array($lastBranch)) {
                    $lastBranch = $lastBranch[$keyName];
                } else {
                    $lastBranch = $lastBranch->$keyName;
                }
            } else {
                return $default;
            }
        }

        return $lastBranch;
    }

    public function getData($isAdmin = null)
    {
        $data = array_merge($this->loadConfig(), $this->loadInterfaceLocales());

        $data = $this->prepareStylesheetConfigForOutput($data);
        $data = $this->prepareCustomHeadCodeForOutput($data);

        $restrictedConfig = $data;
        foreach ($this->getRestrictItems($isAdmin) as $name) {
            if (isset($restrictedConfig[$name])) {
                unset($restrictedConfig[$name]);
            }
        }

        return $restrictedConfig;
    }

    protected function loadConfig($reload = false)
    {
        parent::loadConfig($reload);

        // put reference data
        $this->putReferenceData();

        return $this->data;
    }

    public function set($name, $value = null, $dontMarkDirty = false)
    {
        // ignore referenceData setting
        if ($name === 'referenceData') {
            return;
        }

        parent::set($name, $value, $dontMarkDirty);
    }

    protected function putReferenceData(): void
    {
        if (!is_dir(ReferenceData::DIR_PATH)) {
            return;
        }

        foreach (scandir(ReferenceData::DIR_PATH) as $file) {
            if (!is_file(ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . $file)) {
                continue;
            }

            $entityName = str_replace('.json', '', $file);
            $items = @json_decode(file_get_contents(ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . $file), true);
            if (!empty($items)) {
                $this->data['referenceData'][$entityName] = $items;

                // prepare config locales for backward compatibility
                if ($entityName === 'Locale') {
                    $locales = [];
                    foreach ($items as $row) {
                        foreach (self::DEFAULT_LOCALE as $k => $v) {
                            $locales[$row['id']][$k] = isset($row[$k]) ? $row[$k] : $v;
                        }
                        $locales[$row['id']]['name'] = $row['name'];
                        $locales[$row['id']]['language'] = $row['code'] ?? 'en_US';
                        $locales[$row['id']]['fallbackLanguage'] = $row['fallbackLanguage'] ?? null;
                        $locales[$row['id']]['weekStart'] = $locales[$row['id']]['weekStart'] === 'monday' ? 1 : 0;
                    }

                    $this->data['locales'] = $locales;
                }
            }
        }
    }
}