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

class Storage extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if (!$entity->isNew()) {
            if ($entity->isAttributeChanged('type')) {
                throw new BadRequest($this->translate('storageTypeCannotBeChanged', 'exceptions', 'Storage'));
            }
            if ($entity->get('type') === 'local' && $entity->isAttributeChanged('path')) {
                throw new BadRequest($this->translate('storagePathCannotBeChanged', 'exceptions', 'Storage'));
            }
        }

        if ($entity->get('type') === "local") {
            echo '<pre>';
            print_r('123');
            die();
            if (empty($entity->get('path'))) {
                throw new BadRequest("Path is required.");
            }
            if ($entity->isAttributeChanged('path')) {
                $exists = $this
                    ->where(['type' => "local", 'id!=' => $entity->get('id')])
                    ->find();

                $escapedPrefix = preg_quote($entity->get('path'), '/');

                // Build the regular expression pattern
                $pattern = '/^' . $escapedPrefix . '/';


                $result = preg_grep($pattern, array_column($exists->toArray(), 'path'));

                echo '<pre>';
                print_r($pattern);
                die();

                foreach ($exists as $exist) {
                    if (preg_match($pattern, (string)$exist->get('path'))) {
                        echo '<pre>';
                        print_r($exist->toArray());
                        die();
                    }
                }

                echo '<pre>';
                print_r('123');
                die();

                // Your array of strings
                $array = ['upload/files', 'upload/vol-1', 'upload/files/foo'];

// Regular expression to match strings that start with 'upload/files'
                $pattern = '/^upload\/files/';

// Use preg_grep to filter the array
                $result = preg_grep($pattern, $array);

                echo '<pre>';
                print_r($exists->toArray());
                die();

                if (!empty($existed)) {
                    throw new BadRequest($this->translate('storagePathNotUnique', 'exceptions', 'Storage'));
                }
            }
        }
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        $e = $this->getEntityManager()->getRepository('File')
            ->select(['id'])
            ->where([
                'storageId' => $entity->get('id')
            ])
            ->findOne();

        if (!empty($e)) {
            throw new BadRequest($this->translate('storageWithFilesCannotBeRemoved', 'exceptions', 'Storage'));
        }

        parent::beforeRemove($entity, $options);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }

    protected function translate(string $key, string $category, string $scope): string
    {
        return $this->getInjection('language')->translate($key, $category, $scope);
    }
}
