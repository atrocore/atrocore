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
    protected function loadConfig($reload = false)
    {
        parent::loadConfig($reload);

        // put reference data
        if (is_dir(ReferenceData::DIR_PATH)) {
            foreach (scandir(ReferenceData::DIR_PATH) as $file) {
                if (!is_file(ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . $file)) {
                    continue;
                }

                $entityName = str_replace('.json', '', $file);
                $items = @json_decode(file_get_contents(ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . $file), true);
                if (!empty($items)) {
                    $this->data['referenceData'][$entityName] = $items;
                }
            }
        }

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
}
