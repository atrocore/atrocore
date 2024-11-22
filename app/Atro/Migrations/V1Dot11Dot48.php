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

namespace Atro\Migrations;

use Atro\Core\Exceptions\Error;
use Atro\Core\Migration\Base;
use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Core\Utils\Util;

class V1Dot11Dot48 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-11-22 12:00:00');
    }

    public function up(): void
    {
        @mkdir(ReferenceData::DIR_PATH);

        $filePath = ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . 'EmailTemplate.json';
        $result = [];
        if (file_exists($filePath)) {
            $fileData = @json_decode(file_get_contents($filePath), true);
            if (is_array($fileData)) {
                $result = $fileData;
            }
        }

        $query = $this->getConnection()->createQueryBuilder()
            ->select('*')
            ->from('notification_template')
            ->where('deleted = :false')
            ->andWhere('type=:emailType')
            ->setParameter('false', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
            ->setParameter('emailType', 'email')
            ->executeQuery();

        while (($row = $query->fetchAssociative()) !== false) {
            $data = @json_decode($row['data'], true);
            if (empty($data['field'])) {
                continue;
            }

            $result[$row['id']] = [
                'id'      => $row['id'],
                'code'    => $row['id'],
                'name'    => $row['name'],
                'isHtml'  => true,
                'subject' => $data['field']['subject'] ?? '',
                'body'    => $data['field']['body'] ?? '',
            ];

            $this->getConnection()->createQueryBuilder()
                ->delete('notification_template')
                ->where('id=:id')
                ->setParameter('id', $row['id'])
                ->executeQuery();
        }

        file_put_contents(ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . 'EmailTemplate.json', json_encode($result));

        $this->updateComposer('atrocore/core', '^1.11.48');
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited');
    }
}
