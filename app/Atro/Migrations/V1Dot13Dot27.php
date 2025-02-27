<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;

class V1Dot13Dot27 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-02-26 12:00:00');
    }

    public function up(): void
    {
        $referencePath = ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . 'SystemIcon.json';
        $icons = V1Dot13Dot18::getDefaultIcons();

        if (file_exists($referencePath)) {
            $data = json_decode(file_get_contents($referencePath), true);

            if (!empty($data)) {
                $result = [];
                $defaultIconsList = array_column($icons, 'code');

                foreach ($data as $code => $d) {
                    if (!in_array($code, $defaultIconsList)) {
                        $result[$code] = $d;
                    }
                }

                file_put_contents($referencePath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        }

        if (!empty($files = array_column($icons, 'imageName'))) {
            $this
                ->getConnection()
                ->createQueryBuilder()
                ->update('file')
                ->set('deleted', ':true')
                ->where('name in (:names)')
                ->andWhere('mime_type = :type')
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->setParameter('names', $files, Mapper::getParameterType($files))
                ->setParameter('type', 'image/svg+xml')
                ->executeQuery();
        }
    }
}
