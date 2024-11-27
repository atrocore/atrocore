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
use Espo\ORM\Entity;

class HtmlSanitizer extends ReferenceData
{
    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('serviceFactory');
        $this->addDependency('htmlSanitizer');
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if ($entity->isAttributeChanged('configuration') && !empty($entity->get('configuration'))) {
            $parsed = $this->getHtmlSanitizer()->parse($entity->get('configuration'));

            if (!is_array($parsed)) {
                throw new BadRequest($this->getInvalidYamlMessage());
            }
        }
    }

    protected function getInvalidYamlMessage(): string
    {
        return $this->getLanguage()->translate('notValidYaml', 'exceptions', $this->entityName);
    }

    protected function getLanguage(): \Atro\Core\Utils\Language
    {
        return $this->getInjection('language');
    }

    protected function getHtmlSanitizer(): \Atro\Core\Utils\HTMLSanitizer
    {
        return $this->getInjection('htmlSanitizer');
    }
}
