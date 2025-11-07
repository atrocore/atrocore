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

namespace Atro\SelectManagers;

use Atro\Core\SelectManagers\Base;

class AttributeGroup extends Base
{
    /**
     * @param array $result
     */
    protected function boolFilterWithNotLinkedAttributesToClassification(array &$result)
    {
        // get product family attributes
        $classificationAttributes = $this
            ->getEntityManager()
            ->getRepository('ClassificationAttribute')
            ->select(['attributeId'])
            ->where([
                'classificationId' => (string)$this->getBoolFilterParameter('withNotLinkedAttributesToClassification'),
                'channelId' => ''
            ])
            ->find()
            ->toArray();

        if (count($classificationAttributes) > 0) {
            $result['whereClause'][] = [
                'id' => $this->getNotLinkedAttributeGroups($classificationAttributes)
            ];
        }
    }

    /**
     * Get attributeGroups with not linked all related attributes to product or classification
     *
     * @param array $attributes
     *
     * @return array
     */
    protected function getNotLinkedAttributeGroups(array $attributes): array
    {
        // prepare result
        $result = [];

        // get all attribute groups
        $attributeGroups = $this
            ->getEntityManager()
            ->getRepository('AttributeGroup')
            ->select(['id'])
            ->find();

        foreach ($attributeGroups as $attributeGroup) {
            $attr = $attributeGroup->get('attributes')->toArray();

            if (!empty(array_diff(
                array_column($attr, 'id'),
                array_column($attributes, 'attributeId')
            ))) {
                $result[] = $attributeGroup->get('id');
            }
        }

        return $result;
    }

    protected function boolFilterOnlyForEntity(array &$result): void
    {
        $entityName = (string)$this->getBoolFilterParameter('onlyForEntity');
        if (!empty($entityName)) {
            $result['whereClause'][] = [
                'entityId' => $entityName
            ];
        }
    }
}
