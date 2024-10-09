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
use Doctrine\DBAL\ParameterType;

class V1Dot11Dot12 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-10-09 16:00:00');
    }

    public function up(): void
    {
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

        if (!empty($res)) {
            @mkdir('data/reference-data');
            file_put_contents('data/reference-data/Locale.json', json_encode($res));
        }

        // 2. input languages to language entity
    }

    public function down(): void
    {
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
