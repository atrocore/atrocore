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

namespace Atro\Console;

use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Util;

class RefreshTranslations extends AbstractConsole
{
    public static function getDescription(): string
    {
        return 'Refresh translations.';
    }

    public static function getSimplifiedTranslates(array $data, bool $underscore = false): array
    {
        $records = [];
        foreach ($data as $module => $moduleData) {
            foreach ($moduleData as $locale => $localeData) {
                $preparedLocaleData = [];
                self::toSimpleArray($localeData, $preparedLocaleData);
                foreach ($preparedLocaleData as $key => $value) {
                    $records[$key]['name'] = $key;
                    $records[$key]['module'] = $module;
                    if ($underscore) {
                        $records[$key]['is_customized'] = $module === 'custom';
                        $records[$key][strtolower($locale)] = $value;
                    } else {
                        $records[$key]['isCustomized'] = $module === 'custom';
                        $records[$key][Util::toCamelCase(strtolower($locale))] = $value;
                    }
                }
            }
        }

        return $records;
    }

    public static function toSimpleArray(array $data, array &$result, array &$parents = []): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $parents[] = $key;
                self::toSimpleArray($value, $result, $parents);
            } else {
                $result[implode('.', array_merge($parents, [$key]))] = $value;
            }
        }

        if (!empty($parents)) {
            array_pop($parents);
        }
    }
    
    public function run(array $data): void
    {
        $this->refresh();
        $this->getContainer()->get('dataManager')->clearCache();

        self::show('Translations refreshed successfully.', self::SUCCESS);
    }

    public function refresh(): void
    {
        /** @var \Doctrine\DBAL\Connection $conn */
        $conn = $this->getContainer()->get('connection');

        // delete old
        $conn->createQueryBuilder()
            ->delete('translation')
            ->where('is_customized = :customized')
            ->setParameter('customized', false, ParameterType::BOOLEAN)
            ->executeQuery();

        $records = self::getSimplifiedTranslates((new Language($this->getContainer()))->getModulesData(), true);
        foreach ($records as $record) {
            $qb = $conn->createQueryBuilder()
                ->insert('translation')
                ->setValue('id', ':id')
                ->setValue('created_at', ':now')
                ->setValue('modified_at', ':now')
                ->setValue('created_by_id', ':system')
                ->setValue('modified_by_id', ':system')
                ->setParameter('id', Util::generateId())
                ->setParameter('now', date('Y-m-d H:i:s'))
                ->setParameter('system', 'system');
            foreach ($record as $name => $value) {
                $qb->setValue($name, ':' . $name)
                    ->setParameter($name, $value, Mapper::getParameterType($value));
            }
            try {
                $qb->executeQuery();
            } catch (\Throwable $e) {
            }
        }
    }
}
