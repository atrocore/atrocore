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
use Atro\Core\OAuth\UiClient;
use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;

class OauthClient extends Base
{
    protected $internalAttributeList = ['secretHash'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $isUiClient = $entity->id === $this->getConfig()->get(UiClient::CONFIG_KEY);
        $entity->set('isUiClient', $isUiClient);

        // the redirect URI of the user interface follows the site URL
        if ($isUiClient) {
            $entity->set('redirectUris', [$this->getInjection('container')->get(UiClient::class)->getRedirectUri()]);
        }
    }

    public function putAclMeta(Entity $entity): void
    {
        parent::putAclMeta($entity);

        $entity->setAvailableAction('generateSecret', !empty($entity->get('isConfidential')) && $this->getAcl()->check($entity, 'edit'));

        if ($entity->id === $this->getConfig()->get(UiClient::CONFIG_KEY)) {
            $entity->setAvailableAction('delete', false);
        }
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

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }
}
