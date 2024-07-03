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

use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\Base;

class PreviewTemplate extends Base
{

    public function getHtmlPreview($previewTemplateId, $entityId) : string
    {
        $previewTemplate = $this->getRepository()->get($previewTemplateId);

        if(!$previewTemplate){
            throw new NotFound();
        }

        $entity = $this->getEntityManager()->getEntity($previewTemplate->get('entityType'), $entityId);

        if(!$entity){
            throw new NotFound();
        }

        return $this->getInjection('twig')->renderTemplate($previewTemplate->get('template') ?? '', [
            "entities" => [$entity]
        ]);

    }

    protected function init()
    {
        parent::init();
        $this->addDependency('twig');
    }
}