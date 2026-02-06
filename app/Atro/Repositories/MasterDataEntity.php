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

namespace Atro\Repositories;

use Atro\Core\DataManager;
use Atro\Core\Templates\Repositories\Base;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class MasterDataEntity extends Base
{
    public static function getRecordsWithSourceEntities(Connection $conn): array
    {
        return $conn->createQueryBuilder()
            ->select('*')
            ->from('master_data_entity')
            ->where('deleted=:false')
            ->andWhere('source_entity IS NOT NULL')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->isAttributeChanged('sourceEntity') && !empty($entity->get('sourceEntity'))) {
            $this->getDataManager()->rebuild();
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('dataManager');
    }

    protected function getDataManager(): DataManager
    {
        return $this->getInjection('dataManager');
    }
}
