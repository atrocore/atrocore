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

namespace Atro\Services;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotModified;
use Atro\Core\Templates\Services\ReferenceData;

class AttributePanel extends ReferenceData
{
    public function linkEntity($id, $link, $foreignId)
    {
        if ($link === 'attributes') {
            $input = new \stdClass();
            $input->attributePanelId = $id;
            try {
                $this->getServiceFactory()->create('Attribute')->updateEntity($foreignId, $input);
            } catch (NotModified $e) {
                // ignore
            }

            return true;
        }

        throw new BadRequest();
    }

    public function unlinkEntity($id, $link, $foreignId)
    {
        if ($link === 'attributes') {
            $input = new \stdClass();
            $input->attributePanelId = null;
            try {
                $this->getServiceFactory()->create('Attribute')->updateEntity($foreignId, $input);
            } catch (NotModified $e) {
                // ignore
            }

            return true;
        }

        throw new BadRequest();
    }

    public function unlinkAll(string $id, string $link): bool
    {
        if ($link === 'attributes') {
            $attributes = $this->getEntityManager()->getRepository('Attribute')
                ->where([
                    'attributePanelId' => $id
                ])
                ->find();

            foreach ($attributes as $attribute) {
                $input = new \stdClass();
                $input->attributePanelId = null;
                try {
                    $this->getServiceFactory()->create('Attribute')->updateEntity($attribute->get('id'), $input);
                } catch (NotModified $e) {
                    // ignore
                }
            }

            return true;
        }

        throw new BadRequest();
    }
}
