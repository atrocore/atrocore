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
use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Core\Utils\Util;
use Espo\ORM\Entity;

class Style extends ReferenceData
{
    protected $customStylesheetDir = 'client/custom/css';

    protected $customHeadCodeDir = 'client/custom/html';

    public function refreshCache(): void
    {
        $this->getInjection('dataManager')->clearCache();
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if ($entity->isNew() || $entity->isAttributeChanged('code')) {
            $pattern = '/^[a-zA-Z0-9._\-]+$/';
            if (preg_match($pattern, $entity->get('code')) !== 1) {
                throw new BadRequest($this->getInjection('language')->translate('codeShouldBeString', 'exceptions', 'Style'));
            }
        }

        if (!empty($entity->get('customHeadCode'))) {
            Util::createDir($this->customHeadCodeDir);
            $path = $this->getCustomHeadCodePath($entity);

            file_put_contents($path, $entity->get('customHeadCode'));

            $entity->set('customHeadCodePath', $path);
            $entity->set('customHeadCode', null);
        } else if (
            !empty($entity->_input)
            && property_exists($entity->_input, 'customHeadCode')
            && !empty($path = $entity->get('customHeadCodePath'))
            && is_file($path)
        ) {
            unlink($path);
            $entity->set('customHeadCodePath', null);
        }

        if (!empty($entity->get('customStylesheet'))) {
            Util::createDir($this->customStylesheetDir);
            $path = $this->getCustomStylesheetPath($entity);

            file_put_contents($path, $entity->get('customStylesheet'));

            $entity->set('customStylesheetPath', $path);
            $entity->set('customStylesheet', null);
        } else if (
            !empty($entity->_input)
            && property_exists($entity->_input, 'customStylesheet')
            && !empty($path = $entity->get('customStylesheetPath'))
            && is_file($path)
        ) {
            unlink($path);
            $entity->set('customStylesheetPath', null);
        }
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        $this->refreshCache();
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        if ($this->getEntityManager()->getRepository('Preferences')->hasStyle((string)$entity->get('id'))) {
            throw new BadRequest($this->getInjection('language')->translate('styleIsUsed', 'exceptions', 'Style'));
        }

        parent::beforeRemove($entity, $options);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        if (!empty($path = $entity->get('customStylesheetPath')) && is_file($path)) {
            unlink($path);
        }

        if (!empty($path = $this->get('customHeadCodePath')) && is_file($path)) {
            unlink($path);
        }

        $this->refreshCache();
    }

    protected function getCustomStylesheetPath(Entity $entity): string
    {
        return $this->customStylesheetDir . DIRECTORY_SEPARATOR . "custom-css_{$entity->get('code')}.css";
    }

    public function getCustomHeadCodePath(Entity $entity): string
    {
        return $this->customHeadCodeDir . DIRECTORY_SEPARATOR . "head-code_{$entity->get('code')}.html";
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('dataManager');
    }
}
