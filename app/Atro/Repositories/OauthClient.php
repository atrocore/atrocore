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
use Atro\Core\OAuth\TokenRevoker;
use Atro\Core\Templates\Repositories\Base;
use Atro\Core\Utils\PasswordHash;
use Espo\ORM\Entity;

class OauthClient extends Base
{
    /**
     * Sets a new random secret of the client. Only its hash is stored, the secret itself is returned.
     */
    public function generateSecret(Entity $client): string
    {
        $secret = bin2hex(random_bytes(32));

        $client->set('secretHash', $this->getInjection('container')->get(PasswordHash::class)->hash($secret));
        $this->getEntityManager()->saveEntity($client);

        return $secret;
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        $hasClientCredentials = !empty($entity->get('isConfidential')) && !empty($entity->get('clientCredentialsGrant'));

        if (!$hasClientCredentials && empty($entity->get('authorizationCodeGrant'))) {
            throw new BadRequest($this->getInjection('language')->translate('grantRequired', 'exceptions', 'OauthClient'));
        }

        if ($hasClientCredentials && empty($entity->get('userId'))) {
            throw new BadRequest($this->getInjection('language')->translate('userRequiredForClientCredentials', 'exceptions', 'OauthClient'));
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if (!$entity->isNew() && ($entity->isAttributeChanged('secretHash') || ($entity->isAttributeChanged('isActive') && !$entity->get('isActive')))) {
            $this->getTokenRevoker()->revokeClientTokens($entity->id);
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->getTokenRevoker()->revokeClientTokens($entity->id);
    }

    protected function getTokenRevoker(): TokenRevoker
    {
        return $this->getInjection('container')->get(TokenRevoker::class);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }
}
