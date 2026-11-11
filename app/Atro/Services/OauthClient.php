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

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;

class OauthClient extends Base
{
    protected $internalAttributeList = ['secretHash'];

    public function putAclMeta(Entity $entity): void
    {
        parent::putAclMeta($entity);

        $entity->setAvailableAction('generateSecret', !empty($entity->get('isConfidential')) && $this->getAcl()->check($entity, 'edit'));
    }

    public function generateSecret(string $id): string
    {
        $client = $this->getRepository()->get($id);
        if (empty($client)) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($client, 'edit')) {
            throw new Forbidden();
        }

        return $this->getRepository()->generateSecret($client);
    }
}
