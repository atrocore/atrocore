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

class V1Dot11Dot21 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-10-17 17:00:00');
    }

    public function up(): void
    {
        @mkdir('data/reference-data');

        try {
            $records = $this->getConnection()->createQueryBuilder()
                ->select('*')
                ->from('translation')
                ->where('deleted=:false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            $records = [];
        }

        $res = [];
        foreach ($records as $record) {
            foreach ($record as $column => $value) {
                $res[$record['name']][Util::toCamelCase($column)] = $value;
            }
            $res[$record['name']]['code'] = $record['name'];
        }

        file_put_contents('data/reference-data/Translation.json', json_encode($res));

        try {
            $records = $this->getConnection()->createQueryBuilder()
                ->select('*')
                ->from('ui_handler')
                ->where('deleted=:false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            $records = [];
        }

        $res = [];
        foreach ($records as $record) {
            $code = $record['hash'] ?? $record['name'] . ' ' . Util::generateUniqueHash();
            foreach ($record as $column => $value) {
                if ($column === 'hash') {
                    continue;
                }
                $res[$code][Util::toCamelCase($column)] = $value;
            }
            $res[$code]['code'] = $code;
        }

        file_put_contents('data/reference-data/UiHandler.json', json_encode($res));

        $this->updateComposer('atrocore/core', '^1.11.21');
    }

    public function down(): void
    {
    }
}
