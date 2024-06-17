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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class Connection extends Base
{
    public function get($id = null)
    {
        $entity = parent::get($id);
        if (!empty($entity)) {
            $this->setDataFields($entity);
        }

        return $entity;
    }

    public function setDataFields(Entity $entity): void
    {
        foreach ($entity->getDataFields() as $name => $value) {
            $entity->set($name, $value);
        }
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('type') === 'chatgpt') {
            $chatgptConnection = $this->where(['type' => "chatgpt", 'id!=' => $entity->get('id')])
                ->select(['id'])->findOne();
            if (!empty($chatgptConnection)) {
                throw new BadRequest($this->getLanguage()->translate("chatgptShouldBeUnique", "exceptions", $this->entityType));
            }
        }

        parent::beforeSave($entity, $options);
    }
}
