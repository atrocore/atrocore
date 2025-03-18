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
use Espo\ORM\Entity;

class SavedSearch extends Base
{
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        // Clean filter to remove all removed fields
        $data = json_decode(json_encode($entity->get('data')), true);
        foreach ($data as $filterField => $value) {
            $name = explode('-', $filterField)[0];
            if($name === 'id') {
                continue;
            }
            if(!$this->getMetadata()->get(['entityDefs', $entity->get('entityType'), 'fields', $name])) {
                unset($data[$filterField]);
            }
        }
        $entity->set('data', $data);
    }

    public function findEntities($params)
  {
      $params['where'][] = [
          "type" => "or",
          "value" => [
              [
                  "type" => "equals",
                  "attribute" => "userId",
                  "value" => $this->getUser()->id
              ],
              [
                  "type" => "equals",
                  "attribute" => "isPublic",
                  "value" => true
              ]
          ]
      ];
      return parent::findEntities($params);
  }
}
