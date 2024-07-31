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

namespace Atro\Services;

use Atro\Core\Templates\Services\Base;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class NotificationRule extends Base
{
    protected $mandatorySelectAttributeList = ['data'];

    public function prepareEntityForOutput(Entity $entity)
    {

        $templateIds = [];

        parent::prepareEntityForOutput($entity);

        foreach (array_keys($this->getMetadata()->get(['app', 'notificationTransports'], [])) as $transport) {
            $templateFieldId = $transport . 'TemplateId';
            if (!empty($entity->get($templateFieldId))) {
                $templateIds[$entity->get($templateFieldId)] = $transport;
            }
        }

        if (!empty($templateIds)) {
            $templates = $this->getEntityManager()->getConnection()->createQueryBuilder()
                ->select('id, name, type')
                ->from('notification_template')
                ->where('id in (:ids)')
                ->andWhere('deleted = :false')
                ->setParameter('ids', array_keys($templateIds), Mapper::getParameterType(array_keys($templateIds)))
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();

            foreach ($templates as $template) {
                if (!empty($transport = $templateIds[$template['id']])) {
                    $entity->set($transport . 'TemplateName', $template['name']);
                }
            }
        }
    }
}
