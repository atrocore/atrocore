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
use Atro\Core\OAuth\Entities\AccessTokenEntity;
use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

/**
 * A custom claim of an OAuth client: every access token issued to the client carries it. The claims that the
 * authorization server sets itself cannot be overridden.
 */
class OauthClientClaim extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if (in_array((string)$entity->get('name'), AccessTokenEntity::RESERVED_CLAIMS, true)) {
            throw new BadRequest(sprintf(
                $this->container->get('language')->translate('reservedName', 'exceptions', 'OauthClientClaim'),
                $entity->get('name')
            ));
        }

        parent::beforeSave($entity, $options);
    }
}
