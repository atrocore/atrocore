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

use Atro\Core\AttributeFieldConverter;
use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;

class MatchingRule extends Base
{
    protected $mandatorySelectAttributeList = ['matchingId', 'matchingRuleSetId', 'attributeId'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $checkEntity = $entity;
        while (true) {
            if (empty($checkEntity->get('matchingRuleSetId'))) {
                break;
            }
            $res = $this->getRepository()->get($checkEntity->get('matchingRuleSetId'));
            if (!empty($res)) {
                $checkEntity = $res;
            } else {
                break;
            }
        }

        $matching = $checkEntity->get('matching');
        if (!empty($matching)) {
            $entity->set('editable', $this->getAcl()->check($matching, 'edit'));
        }

        if (!empty($entity->get('attributeId'))) {
            $this->putAttributesToMetadata($matching, $entity);
        }

        $entity->set('fieldDefs', $this->getMetadata()->get("entityDefs.{$matching->get('entity')}.fields.{$entity->get('field')}"));
    }

    public function putAttributesToMetadata(\Atro\Entities\Matching $matching, \Atro\Entities\MatchingRule $rule): void
    {
        $entityName = $matching->get('entity');
        if (empty($this->getMetadata()->get("scopes.$entityName.hasAttribute"))) {
            return;
        }

        $attributesIds = [$rule->get('attributeId')];

        $entity = $this->getEntityManager()->getEntity($entityName);

        $attributesDefs = [];
        foreach ($this->getEntityManager()->getRepository('Attribute')->getAttributesByIds($attributesIds) ?? [] as $row) {
            $this->getAttributeFieldConverter()->convert($entity, $row, $attributesDefs);
        }

        foreach ($attributesDefs as $name => $attributeDefs) {
            $this->getMetadata()->set('entityDefs', $entityName, ['fields' => [$name => $attributeDefs]]);
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }

    protected function getAttributeFieldConverter(): AttributeFieldConverter
    {
        return $this->getInjection('container')->get(AttributeFieldConverter::class);
    }
}
