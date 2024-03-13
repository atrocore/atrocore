<?php
/**
* AtroCore Software
*
* This source file is available under GNU General Public License version 3 (GPLv3).
* Full copyright and license information is available in LICENSE.txt, located in the root directory.
*
*  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
*  @license    GPLv3 (https://www.gnu.org/licenses/)
*/

declare(strict_types=1);

namespace Atro\Console;

use Atro\ORM\DB\RDB\Mapper;
use Atro\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Util;
use Espo\ORM\EntityManager;

/**
 * Class RefreshTranslations
 */
class RefreshTranslations extends AbstractConsole
{
    /**
     * Get console command description
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Refresh translations.';
    }

    public static function getSimplifiedTranslates(array $data): array
    {
        $records = [];
        foreach ($data as $module => $moduleData) {
            foreach ($moduleData as $locale => $localeData) {
                $preparedLocaleData = [];
                self::toSimpleArray($localeData, $preparedLocaleData);
                foreach ($preparedLocaleData as $key => $value) {
                    $records[$key]['name'] = $key;
                    $records[$key]['module'] = $module;
                    $records[$key]['isCustomized'] = $module === 'custom';
                    $records[$key][Util::toCamelCase(strtolower($locale))] = $value;
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

    /**
     * Run action
     *
     * @param array $data
     */
    public function run(array $data): void
    {
        $this->refresh();
        $this->getContainer()->get('dataManager')->clearCache();

        self::show('Translations refreshed successfully.', self::SUCCESS);
    }

    public function refresh(): void
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->getContainer()->get('connection');

        // delete old
        $connection->createQueryBuilder()
            ->delete('translation')
            ->where('is_customized = :customized')
            ->setParameter('customized', false, Mapper::getParameterType(false))
            ->executeQuery();

        /** @var EntityManager $em */
        $em = $this->getContainer()->get('entityManager');

        $records = self::getSimplifiedTranslates((new Language($this->getContainer()))->getModulesData());

        foreach ($records as $record) {
            $label = $em->getEntity('Translation');
            $label->set($record);

            try {
                $em->saveEntity($label, ['keepCache' => true]);
            } catch (BadRequest $e) {
                // ignore validation errors
            }
        }
    }
}
