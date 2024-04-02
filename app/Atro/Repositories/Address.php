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

use Atro\Core\Exceptions\NotUnique;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class Address extends Base
{
    /**
     * @inheritDoc
     */
    public function beforeSave(Entity $entity, array $options = array())
    {
        // set name
        $fields = ["phone", "email", "type", "street", "zip", "box", "city", "country", "country_code"];
        $text = join("\n", array_map(function ($field) use ($entity) {
            return empty($entity->get($field)) ? "" : $entity->get($field);
        }, $fields));
        $entity->set('hash', md5('atrocore_salt ' . $text));

        parent::beforeSave($entity, $options);
    }


    public function save(Entity $entity, array $options = [])
    {
        try {
            $result = parent::save($entity, $options);
        } catch (\Throwable $e) {
            // if duplicate
            if ($e instanceof NotUnique) {
                throw new BadRequest($this->getInjection('language')->translate('unique', 'exceptions', 'Address'));
            }
            throw $e;
        }

        return $result;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}
