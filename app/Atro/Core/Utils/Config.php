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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types as FieldTypes;

class Config extends \Espo\Core\Utils\Config
{
    public function getCachedLocales(): array
    {
        $data = $this->container->get('dataManager')->getCacheData('locales');
        if (is_array($data)) {
            return $data;
        }

        /** @var Connection $connection */
        $connection = $this->container->get('connection');

        $qb = $connection->createQueryBuilder();
        $data = $qb
            ->select('l.*, la.code')
            ->from($connection->quoteIdentifier('locale'), 'l')
            ->innerJoin('l', 'language', 'la', 'la.id=l.language_id AND la.deleted=:false')
            ->where('l.deleted = :false')
            ->setParameter('false', false, FieldTypes::BOOLEAN)
            ->fetchAllAssociative();

        $result = [];
        foreach ($data as $row) {
            foreach (self::DEFAULT_LOCALE as $k => $v) {
                $preparedKey = Util::toUnderScore($k);
                $result[$row['id']][$k] = isset($row[$preparedKey]) ? $row[$preparedKey] : $v;
            }
            $result[$row['id']]['name'] = $row['name'];
            $result[$row['id']]['language'] = $row['code'] ?? 'en_US';
            $result[$row['id']]['weekStart'] = $result[$row['id']]['weekStart'] === 'monday' ? 1 : 0;
        }

        $this->container->get('dataManager')->setCacheData('locales', $result);

        return $result;
    }

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