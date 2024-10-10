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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\ParameterType;

class V1Dot11Dot12 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-10-09 16:00:00');
    }

    public function up(): void
    {
        @mkdir('data/reference-data');

        try {
            $locales = $this->getConnection()->createQueryBuilder()
                ->select('*')
                ->from('locale')
                ->where('deleted=:false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            $locales = [];
        }

        $res = [];
        foreach ($locales as $locale) {
            $res[$locale['language']] = [
                'id'                => $locale['id'],
                'code'              => $locale['language'],
                'name'              => $locale['name'],
                'description'       => $locale['description'],
                'decimalMark'       => $locale['decimal_mark'],
                'timeFormat'        => $locale['time_format'],
                'thousandSeparator' => $locale['thousand_separator'],
                'weekStart'         => $locale['week_start'],
                'dateFormat'        => $locale['date_format'],
                'timeZone'          => $locale['time_zone'],
                'createdAt'         => $locale['created_at'],
                'modifiedAt'        => $locale['modified_at'],
                'createdById'       => $locale['created_by_id'],
                'modifiedById'      => $locale['modified_by_id'],
            ];
        }
        file_put_contents('data/reference-data/Locale.json', json_encode($res));


        $mainLanguage = $this->getConfig()->get('mainLanguage', 'en_US');

        $res = [];
        $res[$mainLanguage] = [
            'id'           => Util::generateId(),
            'code'         => $mainLanguage,
            'name'         => $mainLanguage,
            'contentUsage' => 'main',
            'createdAt'    => date('Y-m-d H:i:s'),
        ];
        foreach ($this->getConfig()->get('inputLanguageList', []) as $v) {
            $res[$v] = [
                'id'           => Util::generateId(),
                'code'         => $v,
                'name'         => $v,
                'contentUsage' => 'additional',
                'createdAt'    => date('Y-m-d H:i:s'),
            ];
        }
        file_put_contents('data/reference-data/Language.json', json_encode($res));

        $this->getConfig()->remove('mainLanguage');
        $this->getConfig()->remove('isMultilangActive');
        $this->getConfig()->remove('inputLanguageList');
        $this->getConfig()->save();

        $this->updateComposer('atrocore/core', '^1.11.12');
    }

    public function down(): void
    {
        throw new \Error('Downgrade is prohibited!');
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
